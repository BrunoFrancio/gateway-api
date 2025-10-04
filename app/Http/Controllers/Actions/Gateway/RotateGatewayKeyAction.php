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
        $usuarioId = $requisicao->user()?->id;

        $resultado = $this->servicoDeChaves->rotateKey($gateway, $usuarioId);

        return response()->json([
            'data' => [
                'id'           => $resultado['gateway']->id,
                'nome'         => $resultado['gateway']->nome,
                'key_id'       => $resultado['gateway']->key_id,
                'key_alg'      => $resultado['gateway']->key_alg,
                'key_rotated_at' => $resultado['gateway']->key_rotated_at->toISOString(),
                
                'key_material' => $resultado['key_material_plaintext'],
            ],
            'message' => 'Chave rotacionada com sucesso. ATENÇÃO: Atualize a chave no gateway local imediatamente!'
        ], 200);
    }
}
