<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Actions\Gateway\ListGatewaysAction;
use App\Http\Controllers\Actions\Gateway\ShowGatewayAction;
use App\Http\Controllers\Actions\Gateway\CreateGatewayAction;
use App\Http\Controllers\Actions\Gateway\UpdateGatewayAction;
use App\Http\Controllers\Actions\Gateway\RotateGatewayKeyAction;

Route::get('/health', function () {
    return response()->json(['status' => 'ok']);
});

Route::middleware('auth.basic')->group(function () {
    Route::get('/gateways', ListGatewaysAction::class);
    Route::post('/gateways', CreateGatewayAction::class);
    Route::get('/gateways/{gateway}', ShowGatewayAction::class);
    Route::patch('/gateways/{gateway}', UpdateGatewayAction::class);
    Route::patch('/gateways/{gateway}/rotate', RotateGatewayKeyAction::class);

    // SQL Jobs por Gateway
    Route::post('/gateways/{gateway}/sql-jobs', \App\Http\Controllers\Actions\Gateway\SqlJobs\CriarSqlJobAction::class);
    Route::get('/gateways/{gateway}/sql-jobs/pending', \App\Http\Controllers\Actions\Gateway\SqlJobs\ListarPendentesAction::class);
    Route::post('/gateways/{gateway}/sql-jobs/{job}/ack', \App\Http\Controllers\Actions\Gateway\SqlJobs\ConfirmarAckAction::class);
    Route::post('/gateways/{gateway}/sql-jobs/{job}/fail', \App\Http\Controllers\Actions\Gateway\SqlJobs\RegistrarFalhaAction::class);

});
