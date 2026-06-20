<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\V1\ScheduleController;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::middleware('apikey')->group(function () {

    Route::get('/v1/schedules', [ScheduleController::class, 'index']);
    Route::get('/v1/schedules/{id}', [ScheduleController::class, 'show']);
    Route::post('/v1/schedules', [ScheduleController::class, 'store']);

});