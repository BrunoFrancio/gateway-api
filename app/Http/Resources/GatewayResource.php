<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class GatewayResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'id'             => $this->id,
            'nome'           => $this->nome,
            'ativo'          => $this->ativo,
            'key_id'         => $this->key_id,
            'key_alg'        => $this->key_alg,
            'key_rotated_at' => $this->key_rotated_at?->toISOString(),
            'last_seen_at'   => $this->last_seen_at?->toISOString(),
            'observacoes'    => $this->observacoes,
            'created_at'     => $this->created_at->toISOString(),
            'updated_at'     => $this->updated_at->toISOString(),
        ];
    }
}
