<?php

namespace App\Http\Controllers\Actions\Gateway;

use App\Models\Gateway;

class ShowGatewayAction
{
    public function __invoke(Gateway $gateway)
    {
        $dados = $gateway->toArray();
        unset($dados['key_material_encrypted']);

        return response()->json(['data' => $dados]);
    }
}
