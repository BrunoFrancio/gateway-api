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
        $usuarioAutenticado = $requisicao->user();
        $atorId = $usuarioAutenticado?->id;

        $gatewayAtualizado = $this->servicoDeChaves->rotateKey($gateway, $atorId);

        return response()->json([
            'data' => $gatewayAtualizado->only([
                'id','nome','ativo','key_id','key_alg','key_rotated_at','last_seen_at',
                'observacoes','criado_por','atualizado_por','created_at','updated_at',
            ]),
        ]);
    }
}
