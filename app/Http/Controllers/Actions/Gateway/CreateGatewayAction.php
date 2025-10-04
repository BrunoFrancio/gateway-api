<?php

namespace App\Http\Controllers\Actions\Gateway;

use App\Http\Requests\Gateway\StoreGatewayRequest;
use App\UseCases\Gateway\CreateGatewayUseCase;

class CreateGatewayAction
{
    public function __construct(private CreateGatewayUseCase $casoDeUso) {}

    public function __invoke(StoreGatewayRequest $requisicao)
    {
        $usuarioId = $requisicao->user()?->id;

        $resultado = $this->casoDeUso->executar(
            nome: $requisicao->string('nome'),
            ativo: $requisicao->boolean('ativo', true),
            observacoes: $requisicao->input('observacoes'),
            usuarioId: $usuarioId
        );

        $resposta = [
            'id'          => $resultado['gateway']->id,
            'nome'        => $resultado['gateway']->nome,
            'ativo'       => $resultado['gateway']->ativo,
            'key_id'      => $resultado['gateway']->key_id,
            'key_alg'     => $resultado['gateway']->key_alg,
            'observacoes' => $resultado['gateway']->observacoes,
            'created_at'  => $resultado['gateway']->created_at->toISOString(),

            'key_material' => $resultado['key_material_plaintext'],
        ];

        return response()->json([
            'data' => $resposta,
            'message' => 'Gateway criado com sucesso. ATENÇÃO: Armazene a chave de forma segura, ela não será exibida novamente!'
        ], 201);
    }
}
