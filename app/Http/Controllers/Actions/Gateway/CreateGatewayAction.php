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

        $gateway = $this->casoDeUso->executar(
            nome: $requisicao->string('nome'),
            ativo: $requisicao->boolean('ativo', true),
            observacoes: $requisicao->input('observacoes'),
            usuarioId: $usuarioId
        );

        $dados = $gateway->toArray();
        unset($dados['key_material_encrypted']);

        return response()->json(['data' => $dados], 201);
    }
}
