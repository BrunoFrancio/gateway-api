<?php

namespace App\Http\Controllers\Actions\Gateway\SqlJobs;

use App\Models\Gateway;
use App\Models\GatewaySqlJob;
use App\Services\GatewaySqlService;
use Illuminate\Http\Request;

class ConfirmarAckAction
{
    public function __construct(private GatewaySqlService $servicoDeSql) {}

    public function __invoke(Request $requisicao, Gateway $gateway, GatewaySqlJob $job)
    {
        abort_unless($job->gateway_id === $gateway->id, 404);

        $job = $this->servicoDeSql->confirmarAck($job, optional($requisicao->user())->id);

        return response()->json([
            'mensagem' => 'Confirmação registrada.',
            'data' => [
                'id'     => $job->id,
                'status' => $job->status,
            ],
        ], 200);
    }
}
