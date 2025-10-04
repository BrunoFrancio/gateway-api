<?php

namespace App\Http\Controllers\Actions\Gateway;

use App\Http\Resources\GatewayResource;
use App\Models\Gateway;
use Illuminate\Http\Request;

class ListGatewaysAction
{
    public function __invoke(Request $request)
    {
        $gateways = Gateway::query()
            ->with(['criador:id,name', 'atualizador:id,name'])
            ->when($request->filled('ativo'), function ($consulta) use ($request) {
                $consulta->where('ativo', $request->boolean('ativo'));
            })
            ->orderBy('created_at', 'desc')
            ->paginate($request->integer('per_page', 15));

        return GatewayResource::collection($gateways);
    }
}