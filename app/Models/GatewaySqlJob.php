<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUlids;

class GatewaySqlJob extends Model
{
    use HasUlids;

    protected $table = 'gateway_sql_jobs';

    protected $fillable = [
        'gateway_id',
        'key_id',
        'transit_alg',
        'sql_ciphertext',
        'nonce',
        'tag',
        'status',
        'tentativas',
        'ultima_falha',
        'disponivel_em',
        'criado_por',
        'atualizado_por',
    ];

    protected $casts = [
        'tentativas'   => 'integer',
        'disponivel_em'=> 'datetime',
    ];

    public function gateway()
    {
        return $this->belongsTo(Gateway::class, 'gateway_id');
    }

    public function criadoPor()
    {
        return $this->belongsTo(User::class, 'criado_por');
    }

    public function atualizadoPor()
    {
        return $this->belongsTo(User::class, 'atualizado_por');
    }
}
