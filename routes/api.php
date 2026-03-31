<?php

use App\Http\Controllers\AdminController;
use App\Http\Controllers\AiCompanionController;
use App\Http\Controllers\AppointmentController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\ChatController;
use App\Http\Controllers\JournalController;
use App\Http\Controllers\ListenerChatController;
use App\Http\Controllers\MoodController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\SubscriptionController;
use App\Http\Controllers\TicketController;
use App\Http\Controllers\UserController;
use App\Http\Middleware\CheckAdminRole;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes - Version 1
|--------------------------------------------------------------------------
*/

Route::prefix('v1')->group(function () {

    Route::post('/sessions/start', [AuthController::class, 'guestLogin']);
    Route::post('/auth/login', [AuthController::class, 'apiLogin']);

    Route::middleware(['auth:sanctum'])->group(function () {

        Route::post('/companion/interact', [AiCompanionController::class, 'interact']);
        // Route::get('/mood/daily-vibe', [MoodController::class, 'dailyVibe']);
        Route::get('/notifications', [NotificationController::class, 'show']);

        Route::controller(UserController::class)->group(function () {
            Route::get('/', 'me');
            Route::put('/language', 'updateLanguage');
        });

        Route::prefix('mood')->controller(MoodController::class)->group(function () {
            Route::post('/log', 'store');
            Route::get('/history', 'index');
            Route::get('/check-required', 'checkRequired');
            Route::get('/daily-summary', 'dailySummary');
            Route::get('/weekly-summary', 'weeklySummary');
            Route::get('/daily-vibe', 'dailyVibe');
        });

        Route::post('journal/reflection', [JournalController::class, 'generateReflection']);
        Route::apiResource('journal', JournalController::class);
        
        Route::controller(ChatController::class)->group(function () {
            Route::post('/chat', 'sendMessage');
            Route::get('/chat', 'history');
        });

        Route::post('/listener/messages', [ListenerChatController::class, 'saveMessage']);
        Route::get('/listener/history/{ticket_id}', [ListenerChatController::class, 'getHistory']);
        Route::post('/listener/end-session', [ListenerChatController::class, 'endSession']);
        Route::get('/tickets/status/{status}', [TicketController::class, 'getTicketsByStatus']);
        Route::post('/tickets/create', [TicketController::class, 'create']);
        Route::post('/tickets/pay-confirm', [TicketController::class, 'paymentSuccess']);

        Route::get('/tickets/pool', [TicketController::class, 'listOpen']);
        Route::post('/tickets/{id}/accept', [TicketController::class, 'accept']);

        Route::post('/subscription/purchase', [SubscriptionController::class, 'subscribe']);
        Route::get('/subscription/status', [SubscriptionController::class, 'status']);

        Route::prefix('appointments')->controller(AppointmentController::class)->group(function () {
            Route::post('/create', 'createAppointment');
            Route::get('/pending', 'listPendingAppointments');
            Route::get('/my-schedule', 'mySchedule');
            Route::post('/{id}/accept', 'accept');
            Route::get('/{id}/join', 'getJoinDetails');
            Route::get('/my-history', [AppointmentController::class, 'getAppointmentsOfUser']);
            Route::post('/close', [AppointmentController::class, 'closingAppointments']);
        });
    });

    Route::middleware(['auth:sanctum', CheckAdminRole::class])->prefix('admin')->group(function () {
        Route::get('/dashboard', [AdminController::class, 'dashboard']);
        Route::post('/approve/{id}', [AdminController::class, 'approveUser']);
    });
});
