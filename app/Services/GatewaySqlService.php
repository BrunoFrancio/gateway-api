<?php

namespace App\Services;

use App\Models\Gateway;
use App\Models\GatewaySqlJob;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class GatewaySqlService
{
    /**
     * Cifra o SQL para trânsito, usando a chave do próprio Gateway.
     * - Preferência: libsodium (XChaCha20-Poly1305)
     * - Fallback: OpenSSL (AES-256-GCM)
     *
     * Observação importante:
     *   Em App\Models\Gateway, o campo 'key_material_encrypted' usa cast 'encrypted:string'.
     *   Isso significa que AO LER o atributo, ele já vem em TEXTO-PLANO (base64), então
     *   NÃO devemos chamar decrypt()/Crypt::decryptString() aqui.
     *
     * @return array{ciphertext_b64:string, nonce_b64:string, tag_b64:?string, alg:string, key_id:string}
     */
    public function cifrarParaTransito(Gateway $gateway, string $sqlEmTextoPlano): array
    {
        // Já vem em texto-plano (base64) por causa do cast 'encrypted:string'
        $chaveBase64  = $gateway->key_material_encrypted;
        $chaveBinaria = base64_decode($chaveBase64, true);

        if ($chaveBinaria === false) {
            throw new RuntimeException('Falha ao decodificar a chave do gateway (base64 inválido).');
        }

        // Esperamos 32 bytes para XChaCha20/AES-256
        if (strlen($chaveBinaria) !== 32) {
            throw new RuntimeException('Material de chave inválido: esperado 32 bytes.');
        }

        // Preferência: libsodium (XChaCha20-Poly1305 IETF)
        if (extension_loaded('sodium') && function_exists('sodium_crypto_aead_xchacha20poly1305_ietf_encrypt')) {
            $nonce = random_bytes(SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_NPUBBYTES);
            $aad   = ''; // NUNCA null

            $ciphertext = sodium_crypto_aead_xchacha20poly1305_ietf_encrypt(
                $sqlEmTextoPlano,
                $aad,
                $nonce,
                $chaveBinaria
            );

            return [
                'ciphertext_b64' => base64_encode($ciphertext),
                'nonce_b64'      => base64_encode($nonce),
                'tag_b64'        => null,
                'alg'            => 'xchacha20poly1305@libsodium',
                'key_id'         => $gateway->key_id ?? 'key_v1',
            ];
        }

        // Fallback: OpenSSL AES-256-GCM
        $metodo = 'aes-256-gcm';
        $ivLen  = openssl_cipher_iv_length($metodo);
        if ($ivLen === false) {
            throw new RuntimeException('Cipher IV length inválido para AES-256-GCM.');
        }

        $nonce = random_bytes($ivLen);
        $tag   = '';

        $ciphertext = openssl_encrypt(
            $sqlEmTextoPlano,
            $metodo,
            $chaveBinaria,
            OPENSSL_RAW_DATA,
            $nonce,
            $tag
        );

        if ($ciphertext === false) {
            throw new RuntimeException('Falha ao cifrar com OpenSSL AES-256-GCM.');
        }

        return [
            'ciphertext_b64' => base64_encode($ciphertext),
            'nonce_b64'      => base64_encode($nonce),
            'tag_b64'        => base64_encode($tag),
            'alg'            => 'aes-256-gcm@openssl',
            'key_id'         => $gateway->key_id ?? 'key_v1',
        ];
    }

    /**
     * Enfileira um SQL cifrado para um gateway.
     */
    public function enfileirarSql(Gateway $gateway, string $sqlEmTextoPlano, ?int $autorId = null, ?string $disponivelEm = null): GatewaySqlJob
    {
        return DB::transaction(function () use ($gateway, $sqlEmTextoPlano, $autorId, $disponivelEm) {
            $pacote = $this->cifrarParaTransito($gateway, $sqlEmTextoPlano);

            $job = GatewaySqlJob::create([
                'gateway_id'      => $gateway->id,
                'key_id'          => $pacote['key_id'],
                'transit_alg'     => $pacote['alg'],
                'sql_ciphertext'  => $pacote['ciphertext_b64'],
                'nonce'           => $pacote['nonce_b64'],
                'tag'             => $pacote['tag_b64'],
                'status'          => 'pending',
                'tentativas'      => 0,
                'disponivel_em'   => $disponivelEm,
                'criado_por'      => $autorId,
                'atualizado_por'  => $autorId,
            ]);

            Log::channel('gateway_sql')->info('sql_job_criado', [
                'job_id'     => $job->id,
                'gateway_id' => $gateway->id,
                'alg'        => $job->transit_alg,
                'key_id'     => $job->key_id,
                'status'     => $job->status,
            ]);

            return $job;
        });
    }

    public function marcarComoEnviado(GatewaySqlJob $job, ?int $atorId = null): GatewaySqlJob
    {
        $job->forceFill([
            'status'         => 'sent',
            'tentativas'     => $job->tentativas + 1,
            'atualizado_por' => $atorId,
        ])->save();

        Log::channel('gateway_sql')->info('sql_job_enviado', [
            'job_id'     => $job->id,
            'gateway_id' => $job->gateway_id,
            'status'     => $job->status,
            'tentativas' => $job->tentativas,
        ]);

        return $job;
    }

    public function confirmarAck(GatewaySqlJob $job, ?int $atorId = null): GatewaySqlJob
    {
        $job->forceFill([
            'status'         => 'ack',
            'atualizado_por' => $atorId,
        ])->save();

        Log::channel('gateway_sql')->info('sql_job_ack', [
            'job_id'     => $job->id,
            'gateway_id' => $job->gateway_id,
            'status'     => $job->status,
        ]);

        return $job;
    }

    public function registrarFalha(GatewaySqlJob $job, string $mensagem, ?int $atorId = null): GatewaySqlJob
    {
        $job->forceFill([
            'status'         => 'failed',
            'tentativas'     => $job->tentativas + 1,
            'ultima_falha'   => $mensagem,
            'atualizado_por' => $atorId,
        ])->save();

        Log::channel('gateway_sql')->warning('sql_job_falha', [
            'job_id'     => $job->id,
            'gateway_id' => $job->gateway_id,
            'status'     => $job->status,
            'mensagem'   => $mensagem,
            'tentativas' => $job->tentativas,
        ]);

        return $job;
    }
}
