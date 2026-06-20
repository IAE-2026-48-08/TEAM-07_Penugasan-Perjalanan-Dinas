<?php

use App\Http\Controllers\Api\V1\VehicleController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')
    ->middleware('iaekey')
    ->group(function () {
        Route::get('/vehicles', [VehicleController::class, 'index']);
        Route::get('/vehicles/{id}', [VehicleController::class, 'show']);
        Route::post('/vehicles', [VehicleController::class, 'store']);
        Route::patch('/vehicles/{id}', [VehicleController::class, 'update']);
    });

Route::prefix('internal')
    ->middleware('iaekey')
    ->group(function () {
        Route::patch('/vehicles/{id}/status', [VehicleController::class, 'updateStatus']);
    });
