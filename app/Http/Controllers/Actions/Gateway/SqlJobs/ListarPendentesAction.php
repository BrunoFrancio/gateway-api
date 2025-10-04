<?php

namespace App\Http\Controllers\Actions\Gateway\SqlJobs;

use App\Models\Gateway;
use App\Models\GatewaySqlJob;
use App\Services\GatewaySqlService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class ListarPendentesAction
{
    public function __construct(private GatewaySqlService $servicoDeSql) {}

    public function __invoke(Request $requisicao, Gateway $gateway)
    {
        $limite = max(1, min((int)$requisicao->integer('limit', 10), 100));

        $consulta = GatewaySqlJob::query()
            ->where('gateway_id', $gateway->id)
            ->where('status', 'pending')
            ->where(function ($q) {
                $q->whereNull('disponivel_em')
                  ->orWhere('disponivel_em', '<=', Carbon::now());
            })
            ->orderBy('created_at', 'asc')
            ->limit($limite)
            ->get();

        foreach ($consulta as $job) {
            $this->servicoDeSql->marcarComoEnviado($job, optional($requisicao->user())->id);
        }

        // Retornar payload cifrado
        $itens = $consulta->map(function (GatewaySqlJob $job) {
            return [
                'id'            => $job->id,
                'gateway_id'    => $job->gateway_id,
                'status'        => $job->status,
                'transit_alg'   => $job->transit_alg,
                'key_id'        => $job->key_id,
                'ciphertext'    => $job->sql_ciphertext,
                'nonce'         => $job->nonce,
                'tag'           => $job->tag,
                'disponivel_em' => optional($job->disponivel_em)->toISOString(),
                'created_at'    => optional($job->created_at)->toISOString(),
            ];
        });

        return response()->json(['data' => $itens], 200);
    }
}
