<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required',
            'device_name' => 'nullable|string',
        ]);

        $user = User::where('email', $request->email)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid login credentials',
                'errors' => ['email' => ['The provided credentials do not match our records.']]
            ], 401);
        }

        if ($user->status !== 'active') {
            return response()->json([
                'success' => false,
                'message' => 'Account is suspended or inactive',
            ], 403);
        }

        $user->update(['last_login_at' => now()]);
        $token = $user->createToken($request->device_name ?? 'flutter-device')->plainTextToken;

        return response()->json([
            'success' => true,
            'message' => 'Authentication successful',
            'data' => [
                'token' => $token,
                'user' => [
                    'uuid' => $user->uuid,
                    'name' => $user->name,
                    'email' => $user->email,
                    'phone' => $user->phone,
                    'role' => is_object($user->role) ? $user->role->value : $user->role,
                    'avatar_url' => $user->avatar_url,
                ],
                'profile' => $user->agentProfile,
                'subscription' => $user->activeSubscription,
            ]
        ]);
    }

    public function me(Request $request)
    {
        $user = $request->user();
        return response()->json([
            'success' => true,
            'data' => [
                'user' => [
                    'uuid' => $user->uuid,
                    'name' => $user->name,
                    'email' => $user->email,
                    'phone' => $user->phone,
                    'role' => is_object($user->role) ? $user->role->value : $user->role,
                    'avatar_url' => $user->avatar_url,
                ],
                'profile' => $user->agentProfile,
                'subscription' => $user->activeSubscription,
            ]
        ]);
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();
        return response()->json(['success' => true, 'message' => 'Logged out successfully']);
    }
}
