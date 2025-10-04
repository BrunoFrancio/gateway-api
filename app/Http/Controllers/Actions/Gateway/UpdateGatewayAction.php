<?php

namespace App\Http\Controllers\Actions\Gateway;

use App\Http\Requests\Gateway\UpdateGatewayRequest;
use App\Models\Gateway;
use App\UseCases\Gateway\UpdateGatewayUseCase;

class UpdateGatewayAction
{
    public function __construct(private UpdateGatewayUseCase $casoDeUso) {}

    public function __invoke(UpdateGatewayRequest $requisicao, Gateway $gateway)
    {
        $usuarioId = $requisicao->user()?->id;

        $gatewayAtualizado = $this->casoDeUso->executar(
            gateway: $gateway,
            dados: [
                'nome'         => $requisicao->has('nome') ? (string) $requisicao->string('nome') : null,
                'ativo'        => $requisicao->has('ativo') ? $requisicao->boolean('ativo') : null,
                'observacoes'  => $requisicao->exists('observacoes') ? $requisicao->input('observacoes') : null,
            ],
            usuarioId: $usuarioId
        );

        $dados = $gatewayAtualizado->toArray();
        unset($dados['key_material_encrypted']);

        return response()->json(['data' => $dados]);
    }
}
