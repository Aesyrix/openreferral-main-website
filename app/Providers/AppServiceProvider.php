<?php

namespace App\Providers;

use App\Models\Lead;
use App\Models\User;
use App\Policies\LeadPolicy;
use App\Policies\UserPolicy;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        $this->registerRateLimiters();
        $this->registerGates();

        if ($this->app->environment('production')) {
            URL::forceScheme('https');
        }
    }

    /** Limits for the mobile auth surface (BACKEND_GUIDE §10). */
    private function registerRateLimiters(): void
    {
        // OTP sends: per-email and per-IP, whichever trips first.
        RateLimiter::for('otp', fn ($request) => [
            Limit::perMinutes(10, 5)->by('otp-email:'.strtolower((string) $request->input('email'))),
            Limit::perMinutes(10, 15)->by('otp-ip:'.$request->ip()),
        ]);

        RateLimiter::for('auth', fn ($request) => [
            Limit::perMinute(10)->by('auth-email:'.strtolower((string) $request->input('email'))),
            Limit::perMinute(30)->by('auth-ip:'.$request->ip()),
        ]);

        RateLimiter::for('api', fn ($request) => Limit::perMinute(120)
            ->by($request->user()?->id ?: $request->ip()));
    }

    private function registerGates(): void
    {
        Gate::policy(Lead::class, LeadPolicy::class);
        Gate::policy(User::class, UserPolicy::class);

        // Coarse role gates used by the dashboard (§2 permissions matrix).
        Gate::define('access-dashboard', fn (User $user) => $user->isStaff() && $user->isActive());
        Gate::define('manage-agents', fn (User $user) => $user->isStaff() && $user->isActive());
        Gate::define('manage-leads', fn (User $user) => $user->isStaff() && $user->isActive());
        Gate::define('manage-staff', fn (User $user) => $user->isAdmin() && $user->isActive());
        Gate::define('view-revenue', fn (User $user) => $user->isAdmin() && $user->isActive());
        Gate::define('manage-content', fn (User $user) => $user->isAdmin() && $user->isActive());
        Gate::define('manage-settings', fn (User $user) => $user->isAdmin() && $user->isActive());
    }
}
