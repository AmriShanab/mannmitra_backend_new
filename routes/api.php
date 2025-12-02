<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\ChatController;
use App\Http\Controllers\JournalController;
use App\Http\Controllers\MoodController;
use App\Http\Controllers\UserController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {

    Route::post('/sessions/start', [AuthController::class, 'guestLogin']);

    Route::middleware('auth:sanctum')->prefix('user')->group(function () {
        Route::controller(UserController::class)->group(function() {
            Route::get('/', 'me');
            Route::put('/language', 'updateLanguage');
        });

        // MOOD TRACKING ROUTES
        Route::prefix('mood')->controller(MoodController::class)->group(function() {
            Route::post('/log', 'store');
            Route::get('/history', 'index');
            Route::get('/check-required', 'checkRequired');
            Route::get('/daily-summary', 'dailySummary');
            Route::get('/weekly-summary', 'weeklySummary');
        });

        // Journal 
        Route::apiResource('journal', JournalController::class);

        // Chat
        Route::controller(ChatController::class)->group(function() {
            Route::post('/chat', 'sendMessage');
            Route::get('/chat', 'history');
        });
    });
});
