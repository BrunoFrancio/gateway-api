<?php

namespace App\UseCases\Gateway;

use App\Models\Gateway;
use App\Services\GatewayKeyService;

class CreateGatewayUseCase
{
    public function __construct(private GatewayKeyService $servicoDeChaves) {}

    /**
     * @return array{gateway: Gateway, key_material_plaintext: string}
     */
    public function executar(string $nome, bool $ativo, ?string $observacoes, ?int $usuarioId): array
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