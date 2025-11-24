<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\MoodController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {

    Route::post('/sessions/start', [AuthController::class, 'guestLogin']);

    Route::middleware('auth:sanctum')->group(function () {
        Route::get('/user', function (\Illuminate\Http\Request $request) {
            return $request->user();
        });

        // MOOD TRACKING ROUTES
        Route::prefix('mood')->controller(MoodController::class)->group(function() {
            Route::post('/log', 'store');
            Route::get('/history', 'index');
            Route::get('/check-required', 'checkRequired');
            Route::get('/daily-summary', 'dailySummary');
        });
    });
});
