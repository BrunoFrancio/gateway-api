<?php

namespace App\UseCases\Gateway;

use App\Models\Gateway;
use App\Models\GatewayAudit;
use Illuminate\Support\Facades\Request;

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

        GatewayAudit::create([
            'gateway_id' => $gateway->id,
            'acao'       => 'update',
            'old_key_id' => null,
            'new_key_id' => null,
            'ator_id'    => auth()->id(),
            'ip'         => Request::ip(),
            'user_agent' => Request::header('User-Agent'),
        ]);

        return $gateway->fresh();
    }
}
