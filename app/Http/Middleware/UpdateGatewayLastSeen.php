<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class UpdateGatewayLastSeen
{
    /**
     * Atualiza o last_seen_at quando gateway faz request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $gateway = $request->route('gateway');
        
        if ($gateway && $gateway instanceof \App\Models\Gateway) {
            $gateway->update(['last_seen_at' => now()]);
        }

        return $response;
    }
}
