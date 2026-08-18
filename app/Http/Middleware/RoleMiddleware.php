<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class RoleMiddleware
{
    public function handle(Request $request, Closure $next, ...$roles)
    {
        $user = $request->user();

        $statusVal = is_object($user->status) ? $user->status->value : (string)$user->status;

        if (!$user || $statusVal !== 'active') {
            if ($request->expectsJson()) {
                return response()->json(['success' => false, 'message' => 'Account inactive or unauthenticated'], 401);
            }
            auth()->logout();
            return redirect()->route('login')->withErrors(['email' => 'Your account is inactive.']);
        }

        $userRole = is_object($user->role) ? $user->role->value : (string)$user->role;

        if (!in_array($userRole, $roles)) {
            if ($request->expectsJson()) {
                return response()->json(['success' => false, 'message' => 'Unauthorized access'], 403);
            }
            abort(403, 'Unauthorized access to this portal.');
        }

        return $next($request);
    }
}
