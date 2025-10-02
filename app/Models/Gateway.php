<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUlids;

class Gateway extends Model
{
    use HasUlids;

    protected $table = 'gateways';

    protected $fillable = [
        'nome',
        'ativo',
        'key_id',
        'key_alg',
        'key_material_encrypted',
        'key_rotated_at',
        'last_seen_at',
        'observacoes',
        'criado_por',
        'atualizado_por',
    ];

    protected $casts = [
        'ativo' => 'boolean',
        'key_rotated_at' => 'datetime',
        'last_seen_at' => 'datetime',
        'key_material_encrypted' => 'encrypted:string',
    ];
}
