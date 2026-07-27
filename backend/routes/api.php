<?php

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\CategoryController;
use App\Http\Controllers\Api\V1\ListingController;
use App\Http\Controllers\Api\V1\ListingImageController;
use App\Http\Controllers\Api\V1\LocationController;
use App\Http\Controllers\Api\V1\ProfileController;
use App\Http\Controllers\Api\V1\SearchController;
use App\Http\Controllers\Api\V1\UserListingController;
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

    Route::prefix('categories')->group(function (): void {
        Route::get('/', [CategoryController::class, 'index']);
        Route::get('/tree', [CategoryController::class, 'tree']);
        Route::get('/{slug}/attributes', [CategoryController::class, 'attributes']);
        Route::get('/{slug}', [CategoryController::class, 'show']);
    });

    Route::prefix('locations')->group(function (): void {
        Route::get('/', [LocationController::class, 'index']);
        Route::get('/tree', [LocationController::class, 'tree']);
    });

    Route::prefix('search')->group(function (): void {
        Route::middleware('throttle:search-suggestions')->get('/suggestions', [SearchController::class, 'suggestions']);
        Route::middleware('throttle:search-popular')->get('/popular', [SearchController::class, 'popular']);
        Route::middleware('throttle:search')->get('/', [SearchController::class, 'index']);
    });

    Route::prefix('listings')->group(function (): void {
        Route::get('/', [ListingController::class, 'index']);
        Route::get('/latest', [ListingController::class, 'latest']);
        Route::get('/{id}/similar', [ListingController::class, 'similar'])->whereUuid('id');
        Route::get('/{id}', [ListingController::class, 'show'])->whereUuid('id');

        Route::middleware(['auth:api', 'account.active', 'phone.verified', 'throttle:listing-write'])->group(function (): void {
            Route::post('/', [ListingController::class, 'store']);
            Route::put('/{id}', [ListingController::class, 'update'])->whereUuid('id');
            Route::delete('/{id}', [ListingController::class, 'destroy'])->whereUuid('id');
            Route::post('/{id}/submit', [ListingController::class, 'submit'])->whereUuid('id');
            Route::post('/{id}/pause', [ListingController::class, 'pause'])->whereUuid('id');
            Route::post('/{id}/activate', [ListingController::class, 'activate'])->whereUuid('id');
            Route::post('/{id}/mark-sold', [ListingController::class, 'markSold'])->whereUuid('id');
            Route::post('/{id}/renew', [ListingController::class, 'renew'])->whereUuid('id');
            Route::post('/{id}/archive', [ListingController::class, 'archive'])->whereUuid('id');
            Route::post('/{id}/restore', [ListingController::class, 'restore'])->whereUuid('id');
        });

        Route::middleware(['auth:api', 'account.active', 'phone.verified', 'throttle:listing-image'])->group(function (): void {
            Route::post('/{id}/images', [ListingImageController::class, 'store'])->whereUuid('id');
            Route::put('/{id}/images/reorder', [ListingImageController::class, 'reorder'])->whereUuid('id');
            Route::delete('/{id}/images/{imageId}', [ListingImageController::class, 'destroy'])->whereUuid('id')->whereUuid('imageId');
        });
    });

    Route::middleware(['auth:api', 'account.active'])->prefix('users/me')->group(function (): void {
        Route::get('/listings', [UserListingController::class, 'index']);
        Route::get('/listings/{id}/statistics', [UserListingController::class, 'statistics'])->whereUuid('id');
    });
});
