<?php

namespace App\Http\Controllers\Actions\Gateway;

use App\Models\Gateway;
use App\Services\GatewayKeyService;
use Illuminate\Http\Request;

class RotateGatewayKeyAction
{
    public function __construct(private GatewayKeyService $servicoDeChaves) {}

    public function __invoke(Request $requisicao, Gateway $gateway)
    {
        $usuarioId = optional($requisicao->user())->id;

        $gatewayAtualizado = $this->servicoDeChaves->rotateKey($gateway, $usuarioId);

        return response()->json([
            'mensagem' => 'Chave rotacionada com sucesso.',
            'data' => [
                'id'             => $gatewayAtualizado->id,
                'nome'           => $gatewayAtualizado->nome,
                'key_id'         => $gatewayAtualizado->key_id,
                'key_alg'        => $gatewayAtualizado->key_alg,
                'key_rotated_at' => optional($gatewayAtualizado->key_rotated_at)->toISOString(),
                'atualizado_por' => $gatewayAtualizado->atualizado_por,
                'updated_at'     => optional($gatewayAtualizado->updated_at)->toISOString(),
            ],
        ], 200);
    }
}
