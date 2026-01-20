<?php

use App\Http\Controllers\AdminController;
use App\Http\Controllers\AppointmentController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\ChatController;
use App\Http\Controllers\JournalController;
use App\Http\Controllers\ListenerChatController;
use App\Http\Controllers\MoodController;
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

    // --- 1. Public Routes ---
    Route::post('/sessions/start', [AuthController::class, 'guestLogin']);
    Route::post('/auth/login', [AuthController::class, 'apiLogin']);

    // --- 2. Authenticated Routes (Sanctum & Session Compatible) ---
    Route::middleware(['auth:sanctum'])->group(function () {

        // User Profile
        Route::controller(UserController::class)->group(function () {
            Route::get('/', 'me');
            Route::put('/language', 'updateLanguage');
        });

        // Mood Tracking
        Route::prefix('mood')->controller(MoodController::class)->group(function () {
            Route::post('/log', 'store');
            Route::get('/history', 'index');
            Route::get('/check-required', 'checkRequired');
            Route::get('/daily-summary', 'dailySummary');
            Route::get('/weekly-summary', 'weeklySummary');
        });

        // Journaling
        Route::post('journal/reflection', [JournalController::class, 'generateReflection']);
        Route::apiResource('journal', JournalController::class);

        // Mobile Chat (Internal App logic)
        Route::controller(ChatController::class)->group(function () {
            Route::post('/chat', 'sendMessage');
            Route::get('/chat', 'history');
        });

        /* |------------------------------------------------------------------
        | LISTENER LIVE CHAT STORAGE
        |------------------------------------------------------------------
        | These routes must be inside auth:sanctum to support mobile,
        | but require 'credentials: include' in JS Fetch for the Web Dashboard.
        */
        Route::post('/listener/messages', [ListenerChatController::class, 'saveMessage']);
        Route::get('/listener/history/{ticket_id}', [ListenerChatController::class, 'getHistory']);
        Route::post('/listener/end-session', [ListenerChatController::class, 'endSession']);
        Route::get('/tickets/status/{status}', [TicketController::class, 'getTicketsByStatus']);
        // User Ticket Flow
        Route::post('/tickets/create', [TicketController::class, 'create']);
        Route::post('/tickets/pay-confirm', [TicketController::class, 'paymentSuccess']);

        // Listener Flow (Dashboard Actions)
        Route::get('/tickets/pool', [TicketController::class, 'listOpen']);
        Route::post('/tickets/{id}/accept', [TicketController::class, 'accept']);

        // Subscription Management
        Route::post('/subscription/purchase', [SubscriptionController::class, 'subscribe']);
        Route::get('/subscription/status', [SubscriptionController::class, 'status']);

        Route::prefix('appointments')->controller(AppointmentController::class)->group(function () {
            Route::post('/create', 'createAppointment');
            Route::get('/pending', 'listPendingAppointments');
            Route::get('/my-schedule', 'mySchedule');
            Route::post('/{id}/accept', 'accept');
            Route::get('/{id}/join', 'getJoinDetails');
            Route::get('/my-history', [AppointmentController::class, 'getAppointmentsOfUser']);
            Route::post('/appointment/close', [App\Http\Controllers\AppointmentController::class, 'closeAppointment']);
        });
    });

    // --- 3. Admin Only Routes ---
    Route::middleware(['auth:sanctum', CheckAdminRole::class])->prefix('admin')->group(function () {
        Route::get('/dashboard', [AdminController::class, 'dashboard']);
        Route::post('/approve/{id}', [AdminController::class, 'approveUser']);
    });
});
