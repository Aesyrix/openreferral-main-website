<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Mobile API guard: only `role=agent` accounts with `status=active` may reach
 * the lead endpoints. Anything else gets the documented
 * `403 { code: "account_not_active", status }` so the app can show its
 * pending-approval / suspended screens (BACKEND_GUIDE §6).
 */
class EnsureActiveAgent
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        if (! $user->isAgent()) {
            return response()->json([
                'code' => 'not_an_agent',
                'message' => 'The mobile app is for agent accounts only.',
            ], 403);
        }

        if (! $user->isActive()) {
            return response()->json([
                'code' => 'account_not_active',
                'status' => $user->status?->value,
                'message' => match ($user->status?->value) {
                    'pending' => 'Your account is awaiting approval.',
                    'suspended' => 'Your account has been suspended.',
                    'rejected' => 'Your account application was not approved.',
                    default => 'Your account is not active.',
                },
            ], 403);
        }

        return $next($request);
    }
}
