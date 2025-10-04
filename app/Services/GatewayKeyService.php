<?php

namespace App\Services;

use App\Models\Gateway;
use App\Models\GatewayAudit;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class GatewayKeyService
{
    public const ALG_M1 = 'app-key@laravel-crypt';

    public function generateKeyMaterial(int $tamanhoEmBytes = 32): string
    {
        return base64_encode(random_bytes($tamanhoEmBytes));
    }

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
     * Cria um gateway com chave e retorna tanto o gateway quanto a chave em texto plano.
     * @return array{gateway: Gateway, key_material_plaintext: string}
     */
    public function createGatewayWithKey(string $nome, ?int $atorId = null, array $atributosExtras = []): array
    {
        return DB::transaction(function () use ($nome, $atorId, $atributosExtras) {
            $materialDaChaveEmTextoPlano = $this->generateKeyMaterial(32);

            $gateway = Gateway::create(array_merge([
                'nome'                   => $nome,
                'ativo'                  => true,
                'key_id'                 => 'key_v1',
                'key_alg'                => self::ALG_M1,
                'key_material_encrypted' => $materialDaChaveEmTextoPlano,
                'criado_por'             => $atorId,
            ], $atributosExtras));

            GatewayAudit::create([
                'gateway_id' => $gateway->id,
                'acao'       => 'criar_chave',
                'old_key_id' => null,
                'new_key_id' => 'key_v1',
                'ator_id'    => $atorId,
            ]);

            Log::channel('gateway_audit')->info('Gateway criado com key_v1', [
                'gateway_id' => $gateway->id,
                'nome'       => $gateway->nome,
                'ator_id'    => $atorId,
            ]);

            return [
                'gateway' => $gateway,
                'key_material_plaintext' => $materialDaChaveEmTextoPlano,
            ];
        });
    }

    /**
     * Rotaciona a chave do gateway.
     * 
     * @return array{gateway: Gateway, key_material_plaintext: string}
     */
    public function rotateKey(Gateway $gateway, ?int $atorId = null): array
    {
        return DB::transaction(function () use ($gateway, $atorId) {
            $identificadorChaveAnterior = $gateway->key_id;
            $identificadorChaveNova     = $this->nextKeyId($identificadorChaveAnterior);

            $materialDaChaveEmTextoPlano = $this->generateKeyMaterial(32);

            $gateway->forceFill([
                'key_id'                 => $identificadorChaveNova,
                'key_alg'                => self::ALG_M1,
                'key_material_encrypted' => $materialDaChaveEmTextoPlano,
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

            Log::info('Chave rotacionada', [
                'gateway_id' => $gateway->id,
                'old_key_id' => $identificadorChaveAnterior,
                'new_key_id' => $identificadorChaveNova,
            ]);

            return [
                'gateway' => $gateway->fresh(),
                'key_material_plaintext' => $materialDaChaveEmTextoPlano,
            ];
        });
    }
}
