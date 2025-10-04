<?php

namespace App\UseCases\Gateway;

use App\Models\Gateway;

class UpdateGatewayUseCase
{
    public function executar(Gateway $gateway, array $dados, ?int $usuarioId): Gateway
    {
        $mudancas = [];

        if (array_key_exists('nome', $dados) && $dados['nome'] !== null) {
            $mudancas['nome'] = (string) $dados['nome'];
        }

        if (array_key_exists('ativo', $dados) && $dados['ativo'] !== null) {
            $mudancas['ativo'] = (bool) $dados['ativo'];
        }

        if (array_key_exists('observacoes', $dados)) {
            $mudancas['observacoes'] = $dados['observacoes'];
        }

        if ($mudancas !== []) {
            $mudancas['atualizado_por'] = $usuarioId;
            $gateway->fill($mudancas)->save();
        }

        return $gateway->fresh();
    }
}
