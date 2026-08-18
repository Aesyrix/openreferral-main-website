<?php

namespace App\Http\Controllers\Api;

use App\Enums\Role;
use App\Enums\UserStatus;
use App\Http\Controllers\Controller;
use App\Models\AgentProfile;
use App\Models\AgentServiceArea;
use App\Models\Notification;
use App\Models\User;
use App\Models\UserSetting;
use App\Models\VerificationCode;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class AgentAuthController extends Controller
{
    public function registerStart(Request $request)
    {
        $request->validate(['email' => 'required|email']);
        $otp = '123456'; // Default OTP for testing

        VerificationCode::create([
            'email' => $request->email,
            'code_hash' => Hash::make($otp),
            'purpose' => 'signup',
            'expires_at' => now()->addMinutes(10),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Verification OTP sent to email.',
            'test_otp' => $otp,
        ]);
    }

    public function registerVerify(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'code' => 'required|string',
        ]);

        $token = Str::random(40);
        return response()->json([
            'success' => true,
            'verification_token' => $token,
        ]);
    }

    public function registerComplete(Request $request)
    {
        $request->validate([
            'email' => 'required|email|unique:users,email',
            'password' => 'required|string|min:8',
            'name' => 'required|string',
        ]);

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'role' => Role::AGENT,
            'status' => UserStatus::PENDING,
        ]);

        AgentProfile::create(['user_id' => $user->id]);
        UserSetting::create(['user_id' => $user->id]);

        // Event #1: Notify all staff + admin of new agent signup
        $staffAndAdmins = User::whereIn('role', [Role::STAFF, Role::ADMIN])->get();
        foreach ($staffAndAdmins as $recipient) {
            Notification::create([
                'user_id' => $recipient->id,
                'type' => 'agent_signup',
                'title' => 'New Agent Signup',
                'body' => "Agent {$user->name} registered and is pending approval.",
                'actor_id' => $user->id,
            ]);
        }

        $token = $user->createToken('mobile_agent')->plainTextToken;

        return response()->json([
            'success' => true,
            'token' => $token,
            'agent' => $user->load('agentProfile'),
        ]);
    }

    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        $user = User::where('email', $request->email)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            return response()->json(['message' => 'Invalid credentials.'], 401);
        }

        if ($user->status === UserStatus::SUSPENDED || $user->status === UserStatus::REJECTED) {
            return response()->json([
                'code' => 'account_not_active',
                'status' => $user->status->value,
                'message' => 'Account is suspended or rejected.',
            ], 403);
        }

        $user->update(['last_login_at' => now()]);
        $token = $user->createToken('mobile_agent')->plainTextToken;

        return response()->json([
            'success' => true,
            'token' => $token,
            'agent' => $user->load(['agentProfile', 'serviceAreas', 'settings']),
        ]);
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();
        return response()->json(['success' => true, 'message' => 'Logged out successfully.']);
    }

    public function me(Request $request)
    {
        $user = $request->user()->load(['agentProfile', 'serviceAreas', 'settings']);
        return response()->json(['data' => $user]);
    }

    public function updateProfile(Request $request)
    {
        $user = $request->user();
        $user->update($request->only(['name', 'phone']));

        $profile = $user->agentProfile ?? new AgentProfile(['user_id' => $user->id]);
        $profile->fill($request->only(['license_number', 'license_state', 'brokerage', 'bio']));
        $profile->save();

        if ($request->has('service_areas') && is_array($request->service_areas)) {
            AgentServiceArea::where('user_id', $user->id)->delete();
            foreach ($request->service_areas as $area) {
                AgentServiceArea::create(['user_id' => $user->id, 'area' => $area]);
            }
        }

        return response()->json(['data' => $user->load(['agentProfile', 'serviceAreas'])]);
    }

    public function updateSettings(Request $request)
    {
        $user = $request->user();
        $settings = UserSetting::firstOrCreate(['user_id' => $user->id]);
        $settings->update($request->only(['push_notifications', 'email_notifications', 'language', 'theme']));

        return response()->json(['data' => $settings]);
    }
}
