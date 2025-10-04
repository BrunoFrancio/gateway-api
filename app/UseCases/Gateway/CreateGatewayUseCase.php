<?php

namespace App\UseCases\Gateway;

use App\Models\Gateway;
use App\Services\GatewayKeyService;

class CreateGatewayUseCase
{
    public function __construct(private GatewayKeyService $servicoDeChaves) {}

    public function executar(string $nome, bool $ativo, ?string $observacoes, ?int $usuarioId): Gateway
    {
        return $this->servicoDeChaves->createGatewayWithKey(
            nome: $nome,
            atorId: $usuarioId,
            atributosExtras: [
                'ativo'       => $ativo,
                'observacoes' => $observacoes,
            ]
        );
    }
}
