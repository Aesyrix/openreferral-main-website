<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ContentController;
use App\Http\Controllers\Api\LeadController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\ProfileController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Mobile agent API  (BACKEND_GUIDE §6)
|--------------------------------------------------------------------------
| Base path: /api. Everything but the auth entry points requires
| `Authorization: Bearer <sanctum token>`. Lead endpoints additionally require
| an *active* agent account (see EnsureActiveAgent).
*/

Route::prefix('auth')->group(function () {
    // OTP endpoints are the abuse surface — throttle them hard.
    Route::middleware('throttle:otp')->group(function () {
        Route::post('register/start', [AuthController::class, 'registerStart']);
        Route::post('password/forgot', [AuthController::class, 'passwordForgot']);
    });

    Route::middleware('throttle:auth')->group(function () {
        Route::post('register/verify', [AuthController::class, 'registerVerify']);
        Route::post('register/complete', [AuthController::class, 'registerComplete']);
        Route::post('login', [AuthController::class, 'login']);
        Route::post('password/verify', [AuthController::class, 'passwordVerify']);
        Route::post('password/reset', [AuthController::class, 'passwordReset']);
    });

    Route::post('logout', [AuthController::class, 'logout'])->middleware('auth:sanctum');
});

// Editable app copy — public so the app can show Terms before signing in.
Route::get('content', [ContentController::class, 'index']);
Route::get('content/{slug}', [ContentController::class, 'show']);

Route::middleware('auth:sanctum')->group(function () {
    // Profile + settings stay reachable while an account is pending approval,
    // so the app can show the awaiting-approval state and let them fix details.
    Route::get('me', [ProfileController::class, 'show']);
    Route::put('me', [ProfileController::class, 'update']);
    Route::post('me/avatar', [ProfileController::class, 'uploadAvatar']);
    Route::post('me/change-password', [ProfileController::class, 'changePassword']);
    Route::get('me/settings', [ProfileController::class, 'settings']);
    Route::put('me/settings', [ProfileController::class, 'updateSettings']);
    Route::delete('me', [ProfileController::class, 'destroy']);

    Route::get('notifications', [NotificationController::class, 'index']);
    Route::get('notifications/unread-count', [NotificationController::class, 'unreadCount']);
    Route::post('notifications/read-all', [NotificationController::class, 'markAllRead']);
    Route::post('notifications/{notification}/read', [NotificationController::class, 'markRead']);

    Route::post('devices', [NotificationController::class, 'registerDevice']);
    Route::delete('devices/{token}', [NotificationController::class, 'removeDevice']);

    // Leads require an approved, active agent.
    Route::middleware('agent.active')->prefix('leads')->group(function () {
        Route::get('/', [LeadController::class, 'index']);
        Route::get('stats', [LeadController::class, 'stats']);
        Route::get('analytics', [LeadController::class, 'analytics']);
        Route::get('{lead}', [LeadController::class, 'show'])->whereNumber('lead');
        Route::post('{lead}/respond', [LeadController::class, 'respond'])->whereNumber('lead');
        Route::post('{lead}/status', [LeadController::class, 'updateStatus'])->whereNumber('lead');
    });
});
