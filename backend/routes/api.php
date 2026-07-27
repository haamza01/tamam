<?php

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\ProfileController;
use App\Http\Controllers\HealthController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function (): void {
    Route::get('/health', HealthController::class);

    Route::prefix('auth')->group(function (): void {
        Route::middleware('throttle:auth-register')->post('/register', [AuthController::class, 'register']);
        Route::middleware('throttle:auth-login')->post('/login', [AuthController::class, 'login']);
        Route::middleware('throttle:auth-password')->post('/forgot-password', [AuthController::class, 'forgotPassword']);
        Route::middleware('throttle:auth-password')->post('/reset-password', [AuthController::class, 'resetPassword']);

        Route::middleware(['refresh.csrf', 'throttle:auth-refresh'])->post('/refresh', [AuthController::class, 'refresh']);

        Route::middleware(['auth:api', 'account.active'])->group(function (): void {
            Route::get('/me', [AuthController::class, 'me']);
            Route::post('/logout', [AuthController::class, 'logout']);
            Route::post('/logout-all', [AuthController::class, 'logoutAll']);
            Route::middleware('throttle:auth-otp')->post('/verify-phone', [AuthController::class, 'verifyPhone']);
            Route::middleware('throttle:auth-otp-resend')->post('/resend-phone-code', [AuthController::class, 'resendPhoneCode']);
        });
    });

    Route::middleware(['auth:api', 'account.active'])->prefix('profile')->group(function (): void {
        Route::get('/', [ProfileController::class, 'show']);
        Route::middleware('throttle:profile-update')->patch('/', [ProfileController::class, 'update']);
        Route::middleware('throttle:profile-avatar')->post('/avatar', [ProfileController::class, 'uploadAvatar']);
        Route::delete('/avatar', [ProfileController::class, 'deleteAvatar']);
    });
});
