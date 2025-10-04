<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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

    protected $hidden = [
        'key_material_encrypted',
    ];

    protected $casts = [
        'ativo'          => 'boolean',
        'key_rotated_at' => 'datetime',
        'last_seen_at'   => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (Gateway $gateway) {
            if (is_null($gateway->criado_por) && auth()->check()) {
                $gateway->criado_por = auth()->id();
            }
        });

        static::updating(function (Gateway $gateway) {
            if (auth()->check()) {
                $gateway->atualizado_por = auth()->id();
            }
        });
    }

    public function auditorias()
    {
        return $this->hasMany(GatewayAudit::class, 'gateway_id');
    }

    public function criador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'criado_por');
    }

    public function atualizador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'atualizado_por');
    }
}
