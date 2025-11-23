<?php

use App\Http\Controllers\AuthController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    // Public Route
    Route::post('/sessions/start', [AuthController::class, 'guestLogin']);
});
