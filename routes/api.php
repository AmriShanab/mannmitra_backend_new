<?php
//
use App\Http\Controllers\ActivityController;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\AiCompanionController;
use App\Http\Controllers\AppointmentController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\ChatController;
use App\Http\Controllers\ExerciseController;
use App\Http\Controllers\ExerciseProgressController;
use App\Http\Controllers\JournalController;
use App\Http\Controllers\ListenerChatController;
use App\Http\Controllers\MoodController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\RealtimeController;
use App\Http\Controllers\SleepLogController;
use App\Http\Controllers\GoalController;
use App\Http\Controllers\SubscriptionController;
use App\Http\Controllers\TicketController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\WhatsAppController;
use App\Http\Middleware\CheckAdminRole;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes - Version 1
|--------------------------------------------------------------------------
*/

Route::prefix('v1')->group(function () {

    Route::post('/sessions/start', [AuthController::class, 'guestLogin'])->middleware('throttle:guest-login');
    Route::post('/auth/login', [AuthController::class, 'apiLogin'])->middleware('throttle:login');
    Route::post('/whatsapp/webhook', [WhatsAppController::class, 'handleWebhook'])->middleware('throttle:60,1');
    Route::post('/webhooks/razorpay', [SubscriptionController::class, 'webhook']);

    Route::middleware(['auth:sanctum'])->group(function () {

        Route::post('/companion/interact', [AiCompanionController::class, 'interact'])->middleware('throttle:companion');
        // Route::get('/mood/daily-vibe', [MoodController::class, 'dailyVibe']);
        Route::get('/notifications', [NotificationController::class, 'index']);
        Route::post('/notifications/read-all', [NotificationController::class, 'markAllRead']);
        Route::post('/notifications/{id}/read', [NotificationController::class, 'markRead']);

        Route::controller(UserController::class)->group(function () {
            Route::get('/', 'me');
            Route::put('/language', 'updateLanguage');
            Route::delete('/account', 'deleteAccount');
        });

        Route::prefix('mood')->controller(MoodController::class)->group(function () {
            Route::post('/log', 'store');
            Route::get('/history', 'index');
            Route::get('/check-required', 'checkRequired');
            Route::get('/daily-summary', 'dailySummary');
            Route::get('/weekly-summary', 'weeklySummary');
            Route::get('/daily-vibe', 'dailyVibe');
        });

        Route::post('journal/reflection', [JournalController::class, 'generateWeeklyReflection'])->middleware('throttle:companion');
        Route::apiResource('journal', JournalController::class);

        Route::controller(ChatController::class)->group(function () {
            Route::post('/chat', 'sendMessage')->middleware('throttle:companion');
            Route::get('/chat', 'history');
        });

        Route::post('/realtime/session', [RealtimeController::class, 'session']);
        Route::post('/listener/messages', [ListenerChatController::class, 'saveMessage']);
        Route::get('/listener/history/{ticket_id}', [ListenerChatController::class, 'getHistory']);
        Route::post('/listener/end-session', [ListenerChatController::class, 'endSession']);
        Route::get('/tickets/status/{status}', [TicketController::class, 'getTicketsByStatus']);
        Route::post('/tickets/create', [TicketController::class, 'create'])->middleware('throttle:payments');
        Route::post('/tickets/pay-confirm', [TicketController::class, 'paymentSuccess']);

        // Listener-only: the pool shows every waiting user's subject.
        Route::middleware('role:listener,admin')->group(function () {
            Route::get('/tickets/pool', [TicketController::class, 'listOpen']);
            Route::post('/tickets/{id}/accept', [TicketController::class, 'accept']);
        });

        Route::get('/subscription/plans', [SubscriptionController::class, 'plans']);
        Route::post('/subscription/create-order', [SubscriptionController::class, 'createOrder'])->middleware('throttle:payments');
        Route::post('/subscription/purchase', [SubscriptionController::class, 'subscribe']);
        Route::get('/subscription/status', [SubscriptionController::class, 'status']);

        // Version 2 of Listener Tickets - Admin Pool and Direct Assignment
        // Route::post('/tickets/create', [TicketController::class, 'createTicket']);




        Route::prefix('appointments')->controller(AppointmentController::class)->group(function () {
            Route::post('/create', 'createAppointment')->middleware('throttle:payments');
            Route::post('/pay-confirm', 'paymentSuccess');
            Route::post('/{id}/cancel-request', 'requestCancellation');
            Route::get('/{id}/join', 'getJoinDetails');
            Route::get('/my-history', 'getAppointmentsOfUser');
            Route::post('/close', 'closingAppointments');

            // Psychiatrist-only: pending requests include patients' notes.
            Route::middleware('role:doctor,admin')->group(function () {
                Route::get('/pending', 'listPendingAppointments');
                Route::get('/my-schedule', 'mySchedule');
                Route::post('/{id}/accept', 'accept');
                Route::post('/{id}/cancel-decision', 'decideCancellation');
            });
        });

        Route::get('/cbt/activities', [ActivityController::class, 'index']);
        Route::get('/cbt/progress', [ExerciseProgressController::class, 'summary']);
        Route::get('/cbt/thought-records', [ExerciseProgressController::class, 'thoughtRecords']);
        Route::get('/cbt/gratitude', [ExerciseProgressController::class, 'gratitude']);
        Route::get('/cbt/goals', [GoalController::class, 'index']);
        Route::post('/cbt/goals/{id}/checkin', [GoalController::class, 'checkIn'])->whereNumber('id');
        Route::get('/sleep/logs', [SleepLogController::class, 'index']);
        Route::post('/sleep/logs', [SleepLogController::class, 'store']);
        Route::post('/cbt/exercises/create-order', [ExerciseController::class, 'createOrder'])->middleware('throttle:payments');
        Route::post('/cbt/exercises/purchase', [ExerciseController::class, 'purchase']);
    });

    Route::middleware(['auth:sanctum', CheckAdminRole::class])->prefix('admin')->group(function () {
        Route::get('/dashboard', [AdminController::class, 'dashboard']);
        Route::post('/approve/{id}', [AdminController::class, 'approveUser']);
        Route::get('/ai-usage', fn(\Illuminate\Http\Request $r) => response()->json([
            'status' => true,
            'data' => app(\App\Ai\AiUsageReport::class)->build(min(90, max(1, (int) $r->query('days', 7)))),
        ]));
    });
});
