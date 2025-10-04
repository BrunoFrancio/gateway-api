<?php

namespace App\Http\Controllers\Actions\Gateway\SqlJobs;

use App\Models\Gateway;
use App\Models\GatewaySqlJob;
use App\Services\GatewaySqlService;
use Illuminate\Http\Request;

class RegistrarFalhaAction
{
    public function __construct(private GatewaySqlService $servicoDeSql) {}

    public function __invoke(Request $requisicao, Gateway $gateway, GatewaySqlJob $job)
    {
        abort_unless($job->gateway_id === $gateway->id, 404);

        $mensagem = (string) $requisicao->input('motivo', 'falha não especificada');
        $job = $this->servicoDeSql->registrarFalha($job, $mensagem, optional($requisicao->user())->id);

        return response()->json([
            'mensagem' => 'Falha registrada.',
            'data' => [
                'id'     => $job->id,
                'status' => $job->status,
            ],
        ], 200);
    }
}
