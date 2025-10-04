<?php

namespace App\UseCases\Gateway;

use App\Models\Gateway;
use App\Services\GatewayKeyService;

class RotateGatewayKeyUseCase
{
    public function __construct(private GatewayKeyService $servicoDeChaves) {}

    public function executar(Gateway $gateway, ?int $usuarioId): Gateway
    {
        return $this->servicoDeChaves->rotateKey($gateway, $usuarioId);
    }
}
