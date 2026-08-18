<?php

use App\Http\Controllers\Admin\ActivityLogController;
use App\Http\Controllers\Admin\AgentController;
use App\Http\Controllers\Admin\AnalyticsController;
use App\Http\Controllers\Admin\ContentController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\LeadController;
use App\Http\Controllers\Admin\RevenueController;
use App\Http\Controllers\Admin\SettingController;
use App\Http\Controllers\Admin\StaffController;
use App\Http\Controllers\Agent\DashboardController as AgentDashboardController;
use App\Http\Controllers\Agent\LeadController as AgentLeadController;
use App\Http\Controllers\Agent\ProfileController as AgentProfileController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\PasswordResetController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\SearchController;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => auth()->check()
    ? redirect()->route(auth()->user()->isStaff() ? 'admin.dashboard' : 'agent.dashboard')
    : redirect()->route('login'));

/*
|--------------------------------------------------------------------------
| Authentication
|--------------------------------------------------------------------------
*/
Route::middleware('guest')->group(function () {
    Route::get('login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('login', [AuthenticatedSessionController::class, 'store'])->middleware('throttle:auth');

    Route::get('forgot-password', [PasswordResetController::class, 'request'])->name('password.request');
    Route::post('forgot-password', [PasswordResetController::class, 'sendCode'])
        ->middleware('throttle:otp')
        ->name('password.email');
    Route::get('reset-password', [PasswordResetController::class, 'form'])->name('password.reset');
    Route::post('reset-password', [PasswordResetController::class, 'reset'])
        ->middleware('throttle:auth')
        ->name('password.update');
});

Route::middleware('auth')->group(function () {
    Route::post('logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');

    Route::get('notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::post('notifications/{id}/read', [NotificationController::class, 'markRead'])->name('notifications.read');
    Route::post('notifications/read-all', [NotificationController::class, 'markAllRead'])->name('notifications.read-all');

    Route::get('search', [SearchController::class, 'index'])->name('global.search');

    /*
    |----------------------------------------------------------------------
    | Dashboard — staff + admin  (BACKEND_GUIDE §7)
    |----------------------------------------------------------------------
    | Admin passes every staff gate; admin-only pages are gated again inside.
    */
    Route::prefix('admin')->middleware('role:staff,admin')->name('admin.')->group(function () {
        Route::get('/', [DashboardController::class, 'index'])->name('dashboard');

        // Agents & the approval workflow
        Route::get('agents', [AgentController::class, 'index'])->name('agents.index');
        Route::get('agents/{id}', [AgentController::class, 'show'])->whereNumber('id')->name('agents.show');
        Route::post('agents/{id}/approve', [AgentController::class, 'approve'])->name('agents.approve');
        Route::post('agents/{id}/reject', [AgentController::class, 'reject'])->name('agents.reject');
        Route::post('agents/{id}/suspend', [AgentController::class, 'suspend'])->name('agents.suspend');
        Route::post('agents/{id}/restore', [AgentController::class, 'restore'])->name('agents.restore');
        Route::post('agents/{id}/verified', [AgentController::class, 'toggleVerified'])->name('agents.verified');
        Route::delete('agents/{id}', [AgentController::class, 'destroy'])->name('agents.destroy');

        // Leads
        Route::get('leads', [LeadController::class, 'index'])->name('leads.index');
        Route::get('leads/create', [LeadController::class, 'create'])->name('leads.create');
        Route::post('leads', [LeadController::class, 'store'])->name('leads.store');
        Route::get('leads/{id}', [LeadController::class, 'show'])->whereNumber('id')->name('leads.show');
        Route::get('leads/{id}/edit', [LeadController::class, 'edit'])->whereNumber('id')->name('leads.edit');
        Route::put('leads/{id}', [LeadController::class, 'update'])->whereNumber('id')->name('leads.update');
        Route::delete('leads/{id}', [LeadController::class, 'destroy'])->whereNumber('id')->name('leads.destroy');
        Route::post('leads/{id}/assign', [LeadController::class, 'assign'])->name('leads.assign');
        Route::post('leads/{id}/unassign', [LeadController::class, 'unassign'])->name('leads.unassign');
        Route::post('leads/{id}/status', [LeadController::class, 'status'])->name('leads.status');
        Route::get('leads/{id}/feedback', [LeadController::class, 'feedback'])->name('leads.feedback');

        // Analytics
        Route::get('analytics', [AnalyticsController::class, 'index'])->name('analytics.index');
        Route::get('analytics/series', [AnalyticsController::class, 'series'])->name('analytics.series');

        /*
        |------------------------------------------------------------------
        | Admin only: staff management, revenue, content, settings, audit
        |------------------------------------------------------------------
        */
        Route::middleware('role:admin')->group(function () {
            Route::get('staff', [StaffController::class, 'index'])->name('staff.index');
            Route::get('staff/create', [StaffController::class, 'create'])->name('staff.create');
            Route::post('staff', [StaffController::class, 'store'])->name('staff.store');
            Route::get('staff/{id}/edit', [StaffController::class, 'edit'])->whereNumber('id')->name('staff.edit');
            Route::put('staff/{id}', [StaffController::class, 'update'])->whereNumber('id')->name('staff.update');
            Route::post('staff/{id}/suspend', [StaffController::class, 'suspend'])->name('staff.suspend');
            Route::post('staff/{id}/restore', [StaffController::class, 'restore'])->name('staff.restore');
            Route::delete('staff/{id}', [StaffController::class, 'destroy'])->whereNumber('id')->name('staff.destroy');

            Route::get('revenue', [RevenueController::class, 'index'])->name('revenue.index');
            Route::get('revenue/series', [RevenueController::class, 'series'])->name('revenue.series');
            Route::post('revenue/transactions', [RevenueController::class, 'storeAdjustment'])->name('revenue.store');
            Route::post('revenue/transactions/{id}/status', [RevenueController::class, 'updateStatus'])->name('revenue.status');

            Route::get('content', [ContentController::class, 'index'])->name('content.index');
            Route::get('content/{slug}/edit', [ContentController::class, 'edit'])->name('content.edit');
            Route::put('content/{slug}', [ContentController::class, 'update'])->name('content.update');

            Route::get('settings', [SettingController::class, 'index'])->name('settings.index');
            Route::post('settings', [SettingController::class, 'update'])->name('settings.update');

            Route::get('audit', [ActivityLogController::class, 'index'])->name('audit.index');
        });
    });

    // Staff land on the same dashboard as admins.
    Route::redirect('staff', '/admin');

    /*
    |----------------------------------------------------------------------
    | Agent web portal (the mobile app is the primary surface)
    |----------------------------------------------------------------------
    */
    Route::prefix('agent')->middleware('role:agent')->name('agent.')->group(function () {
        Route::get('/', [AgentDashboardController::class, 'index'])->name('dashboard');

        Route::get('leads', [AgentLeadController::class, 'index'])->name('leads.index');
        Route::get('leads/{id}', [AgentLeadController::class, 'show'])->whereNumber('id')->name('leads.show');
        Route::post('leads/{id}/respond', [AgentLeadController::class, 'respond'])->name('leads.respond');

        Route::get('profile', [AgentProfileController::class, 'show'])->name('profile');
        Route::post('profile', [AgentProfileController::class, 'update'])->name('profile.update');
        Route::post('profile/password', [AgentProfileController::class, 'changePassword'])->name('profile.password');
        Route::post('profile/settings', [AgentProfileController::class, 'updateSettings'])->name('profile.settings');
    });
});
