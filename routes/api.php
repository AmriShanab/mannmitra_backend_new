<?php

use App\Http\Controllers\AdminController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\ChatController;
use App\Http\Controllers\JournalController;
use App\Http\Controllers\MoodController;
use App\Http\Controllers\UserController;
use App\Http\Middleware\CheckAdminRole;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {

    Route::post('/sessions/start', [AuthController::class, 'guestLogin']);
    Route::post('/auth/login', [AuthController::class, 'login']);

    Route::middleware('auth:sanctum')->group(function () {
        Route::controller(UserController::class)->group(function () {
            Route::get('/', 'me');
            Route::put('/language', 'updateLanguage');
        });


        // MOOD TRACKING ROUTES
        Route::prefix('mood')->controller(MoodController::class)->group(function () {
            Route::post('/log', 'store');
            Route::get('/history', 'index');
            Route::get('/check-required', 'checkRequired');
            Route::get('/daily-summary', 'dailySummary');
            Route::get('/weekly-summary', 'weeklySummary');
        });

        // Journal 
        Route::apiResource('journal', JournalController::class);

        // Chat
        Route::controller(ChatController::class)->group(function () {
            Route::post('/chat', 'sendMessage');
            Route::get('/chat', 'history');
        });
    });

    Route::middleware(['auth:sanctum', CheckAdminRole::class])->prefix('admin')->group(function() {
        Route::get('/dashboard', [AdminController::class, 'dashboard']);
        Route::post('/approve/{id}', [AdminController::class, 'approveUser']);
    });
});
