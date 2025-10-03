<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GatewayAudit extends Model
{
    use HasUlids;

    protected $table = 'gateway_audits';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'gateway_id',
        'acao',
        'old_key_id',
        'new_key_id',
        'ator_id',
        'ip',
        'user_agent',
    ];

    public function gateway(): BelongsTo
    {
        return $this->belongsTo(Gateway::class);
    }
}
