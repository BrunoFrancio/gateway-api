<?php

namespace App\Http\Controllers\Actions\Gateway;

use App\Models\Gateway;
use App\Http\Resources\GatewayResource;

class ShowGatewayAction
{
    public function __invoke(Gateway $gateway)
    {
        $gateway->load(['criador:id,name', 'atualizador:id,name']);
        
        return new GatewayResource($gateway);
    }
}
