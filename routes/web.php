<?php

use App\Http\Controllers\Admin\DashboardController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect('/admin/dashboard');
});

// Temporary Test Route in web.php
Route::get('/test-admin', function () {
    // Log in as User ID 1 (Assuming ID 1 is your Admin)
    \Illuminate\Support\Facades\Auth::loginUsingId(2);
    return redirect('/admin/dashboard');
});

Route::middleware(['auth:sanctum', 'can:admin-access'])->prefix('admin')->group(function() {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('admin.dashboard');
    Route::post('/approve/{id}', function($id) {
        // Call service to approve
        // return back()->with('success', 'User approved!');
    })->name('admin.approve');
    Route::get('/chat-history/{sessionId}', [DashboardController::class, 'getChatHistory'])->name('admin.chat.history');
    Route::post('/alert/resolve/{id}', [DashboardController::class, 'resolveAlert'])->name('admin.alert.resolve');
});
