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
});
