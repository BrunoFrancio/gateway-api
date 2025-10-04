<?php

namespace App\Http\Controllers\Actions\Gateway\SqlJobs;

use App\Http\Requests\Gateway\SqlJobs\CriarSqlJobRequest;
use App\Models\Gateway;
use App\Services\GatewaySqlService;
use Illuminate\Http\Request;

class CriarSqlJobAction
{
    public function __construct(private GatewaySqlService $servicoDeSql) {}

    public function __invoke(CriarSqlJobRequest $requisicao, Gateway $gateway)
    {
        $usuarioId = optional($requisicao->user())->id;
        $sqlEmTextoPlano = $requisicao->input('sql');
        $disponivelEm = $requisicao->input('disponivel_em');

        $job = $this->servicoDeSql->enfileirarSql($gateway, $sqlEmTextoPlano, $usuarioId, $disponivelEm);

        return response()->json([
            'data' => [
                'id'          => $job->id,
                'gateway_id'  => $job->gateway_id,
                'status'      => $job->status,
                'transit_alg' => $job->transit_alg,
                'key_id'      => $job->key_id,
                'disponivel_em' => optional($job->disponivel_em)->toISOString(),
                'created_at'  => optional($job->created_at)->toISOString(),
            ]
        ], 201);
    }
}
