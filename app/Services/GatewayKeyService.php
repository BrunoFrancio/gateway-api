<?php

namespace App\Services;

use App\Models\Gateway;
use App\Models\GatewayAudit;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class GatewayKeyService
{
    /** Descrição do algoritmo usado no M1 (Crypt do Laravel com APP_KEY) */
    public const ALG_M1 = 'app-key@laravel-crypt';

    /** Gera material de chave aleatório (em base64) */
    public function generateKeyMaterial(int $tamanhoEmBytes = 32): string
    {
        return base64_encode(random_bytes($tamanhoEmBytes));
    }

    /** Cifra em repouso usando APP_KEY */
    public function encryptAtRest(string $textoPlano): string
    {
        return Crypt::encryptString($textoPlano);
    }

    /** Decifra em repouso usando APP_KEY */
    public function decryptAtRest(string $textoCifrado): string
    {
        return Crypt::decryptString($textoCifrado);
    }

    /** Calcula o próximo identificador de chave */
    public function nextKeyId(?string $chaveAtual): string
    {
        if (!$chaveAtual) {
            return 'key_v1';
        }

        if (preg_match('/^key_v(\d+)$/', $chaveAtual, $coincidencias)) {
            $versaoAtual = (int) $coincidencias[1];
            return 'key_v' . ($versaoAtual + 1);
        }

        return 'key_v1';
    }

    /**
     * Cria um gateway já com key_v1 e registra auditoria.
     *
     * @param  string $nome
     * @param  int|null $atorId
     * @param  array $atributosExtras
     * @return \App\Models\Gateway
     */
    public function createGatewayWithKey(string $nome, ?int $atorId = null, array $atributosExtras = []): Gateway
    {
        return DB::transaction(function () use ($nome, $atorId, $atributosExtras) {
            $chaveEmTextoPlano = $this->generateKeyMaterial();
            $chaveCifrada      = $this->encryptAtRest($chaveEmTextoPlano);

            /** @var Gateway $gateway */
            $gateway = Gateway::create(array_merge([
                'nome'                   => $nome,
                'ativo'                  => true,
                'key_id'                 => 'key_v1',
                'key_alg'                => self::ALG_M1,
                'key_material_encrypted' => $chaveCifrada,
                'criado_por'             => $atorId,
            ], $atributosExtras));

            GatewayAudit::create([
                'gateway_id' => $gateway->id,
                'acao'       => 'criar_chave',
                'old_key_id' => null,
                'new_key_id' => 'key_v1',
                'ator_id'    => $atorId,
            ]);

            return $gateway;
        });
    }

    /**
     * Rotaciona a chave do gateway para a próxima versão e audita.
     *
     * @param  \App\Models\Gateway $gateway
     * @param  int|null $atorId
     * @return \App\Models\Gateway
     */
    public function rotateKey(Gateway $gateway, ?int $atorId = null): Gateway
    {
        return DB::transaction(function () use ($gateway, $atorId) {
            $identificadorChaveAnterior = $gateway->key_id;
            $identificadorChaveNova     = $this->nextKeyId($identificadorChaveAnterior);

            $chaveEmTextoPlano = $this->generateKeyMaterial();
            $chaveCifrada      = $this->encryptAtRest($chaveEmTextoPlano);

            $gateway->forceFill([
                'key_id'                 => $identificadorChaveNova,
                'key_alg'                => self::ALG_M1,
                'key_material_encrypted' => $chaveCifrada,
                'key_rotated_at'         => Carbon::now(),
                'atualizado_por'         => $atorId,
            ])->save();

            GatewayAudit::create([
                'gateway_id' => $gateway->id,
                'acao'       => 'rotacionar_chave',
                'old_key_id' => $identificadorChaveAnterior,
                'new_key_id' => $identificadorChaveNova,
                'ator_id'    => $atorId,
            ]);

            return $gateway->fresh();
        });
    }
}
