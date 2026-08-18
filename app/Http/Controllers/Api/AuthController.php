<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\AgentResource;
use App\Models\Device;
use App\Models\User;
use App\Models\VerificationCode;
use App\Services\AgentAccountService;
use App\Services\NotificationDispatcher;
use App\Services\OtpService;
use App\Support\ActivityLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Mobile agent authentication (BACKEND_GUIDE §6).
 *
 * Signup is a three-legged OTP flow: start → verify → complete. Password reset
 * mirrors it: forgot → verify → reset.
 */
class AuthController extends Controller
{
    private const TOKEN_NAME = 'mobile_agent';

    public function __construct(
        private OtpService $otp,
        private AgentAccountService $agents,
        private NotificationDispatcher $notifications,
    ) {}

    // -------------------------------------------------------------- Signup

    public function registerStart(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email:rfc', 'max:190'],
        ]);

        $email = Str::lower(trim($data['email']));

        if (User::where('email', $email)->exists()) {
            throw ValidationException::withMessages([
                'email' => ['An account already exists for this email. Try signing in instead.'],
            ]);
        }

        $meta = $this->otp->send($email, VerificationCode::PURPOSE_SIGNUP, ip: $request->ip());

        return response()->json([
            'message' => 'We emailed you a 6-digit verification code.',
            'email' => $email,
        ] + $meta);
    }

    public function registerVerify(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email:rfc'],
            'code' => ['required', 'digits:6'],
        ]);

        $token = $this->otp->verify($data['email'], $data['code'], VerificationCode::PURPOSE_SIGNUP);

        return response()->json([
            'message' => 'Email verified.',
            'verification_token' => $token,
            'expires_in' => OtpService::TOKEN_TTL_MINUTES * 60,
        ]);
    }

    public function registerComplete(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email:rfc', 'unique:users,email'],
            'verification_token' => ['required', 'string'],
            'password' => ['required', 'string', 'min:8'],
            'name' => ['required', 'string', 'max:120'],
            'phone' => ['nullable', 'string', 'max:40'],
        ]);

        $email = Str::lower(trim($data['email']));

        $this->otp->redeemToken($email, $data['verification_token'], VerificationCode::PURPOSE_SIGNUP);

        $agent = $this->agents->register($data['name'], $email, $data['password']);

        if (! empty($data['phone'])) {
            $agent->update(['phone' => $data['phone']]);
        }

        // Event #1 — tell staff + admin there's someone to approve.
        $this->notifications->agentSignedUp($agent);

        ActivityLogger::user('agent.registered', $agent->id, ['email' => $email], $agent);

        return response()->json([
            'message' => 'Account created. An administrator will review it shortly.',
            'token' => $agent->createToken(self::TOKEN_NAME)->plainTextToken,
            'agent' => new AgentResource($this->loadAgent($agent)),
        ], 201);
    }

    // --------------------------------------------------------------- Login

    public function login(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email:rfc'],
            'password' => ['required', 'string'],
            'device_token' => ['nullable', 'string', 'max:400'],
            'platform' => ['nullable', Rule::in(['android', 'ios', 'web'])],
        ]);

        $user = User::where('email', Str::lower(trim($data['email'])))->first();

        if (! $user || ! Hash::check($data['password'], $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['These credentials do not match our records.'],
            ]);
        }

        if (! $user->isAgent()) {
            return response()->json([
                'code' => 'not_an_agent',
                'message' => 'This app is for agent accounts. Staff and admins use the web dashboard.',
            ], 403);
        }

        if (in_array($user->status?->value, ['suspended', 'rejected'], true)) {
            return response()->json([
                'code' => 'account_not_active',
                'status' => $user->status->value,
                'message' => $user->status->value === 'suspended'
                    ? 'Your account has been suspended.'
                    : 'Your account application was not approved.',
            ], 403);
        }

        $user->update(['last_login_at' => now()]);

        // Pending agents may sign in — the app shows its awaiting-approval state.
        $token = $user->createToken(self::TOKEN_NAME)->plainTextToken;

        if (! empty($data['device_token'])) {
            $this->registerDeviceToken($user, $data['device_token'], $data['platform'] ?? 'android');
        }

        return response()->json([
            'token' => $token,
            'agent' => new AgentResource($this->loadAgent($user)),
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($token = $request->input('device_token')) {
            Device::where('user_id', $user->id)->where('token', $token)->delete();
        }

        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Signed out.']);
    }

    // ------------------------------------------------------ Password reset

    public function passwordForgot(Request $request): JsonResponse
    {
        $data = $request->validate(['email' => ['required', 'email:rfc']]);

        $email = Str::lower(trim($data['email']));
        $user = User::where('email', $email)->first();

        // Don't disclose whether the address exists; still rate-limit per email.
        if ($user) {
            $meta = $this->otp->send($email, VerificationCode::PURPOSE_PASSWORD_RESET, $user->name, $request->ip());
        } else {
            $meta = ['resend_in' => $this->otp->resendSeconds(), 'expires_in' => $this->otp->ttlMinutes() * 60];
        }

        return response()->json([
            'message' => 'If that email is registered, we sent it a reset code.',
        ] + $meta);
    }

    public function passwordVerify(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email:rfc'],
            'code' => ['required', 'digits:6'],
        ]);

        $token = $this->otp->verify($data['email'], $data['code'], VerificationCode::PURPOSE_PASSWORD_RESET);

        return response()->json([
            'message' => 'Code verified.',
            'reset_token' => $token,
            'expires_in' => OtpService::TOKEN_TTL_MINUTES * 60,
        ]);
    }

    public function passwordReset(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email:rfc'],
            'reset_token' => ['required', 'string'],
            'password' => ['required', 'string', 'min:8'],
        ]);

        $email = Str::lower(trim($data['email']));

        $this->otp->redeemToken($email, $data['reset_token'], VerificationCode::PURPOSE_PASSWORD_RESET, 'reset_token');

        $user = User::where('email', $email)->firstOrFail();

        $user->update(['password' => $data['password']]);

        // A password change signs out every existing session and device.
        $user->tokens()->delete();
        $user->devices()->delete();

        ActivityLogger::user('user.password_reset', $user->id, [], $user);

        return response()->json(['message' => 'Password updated. Please sign in again.']);
    }

    // ------------------------------------------------------------ Internals

    private function registerDeviceToken(User $user, string $token, string $platform): void
    {
        Device::updateOrCreate(
            ['token' => $token],
            ['user_id' => $user->id, 'platform' => $platform, 'last_seen_at' => now()]
        );
    }

    private function loadAgent(User $user): User
    {
        return $user->load(['agentProfile', 'serviceAreas', 'settings']);
    }
}
