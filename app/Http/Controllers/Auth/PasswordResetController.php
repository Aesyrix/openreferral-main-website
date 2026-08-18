<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\VerificationCode;
use App\Services\OtpService;
use App\Support\ActivityLogger;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Dashboard password reset, using the same emailed 6-digit code as the mobile
 * app so there is one reset mechanism to reason about.
 */
class PasswordResetController extends Controller
{
    public function __construct(private OtpService $otp) {}

    public function request(): View
    {
        return view('auth.forgot-password');
    }

    public function sendCode(Request $request): RedirectResponse
    {
        $data = $request->validate(['email' => ['required', 'email:rfc']]);

        $email = Str::lower(trim($data['email']));
        $user = User::where('email', $email)->first();

        if ($user) {
            $this->otp->send($email, VerificationCode::PURPOSE_PASSWORD_RESET, $user->name, $request->ip());
        }

        return redirect()
            ->route('password.reset', ['email' => $email])
            ->with('success', 'If that email is registered, we just sent it a 6-digit reset code.');
    }

    public function form(Request $request): View
    {
        return view('auth.reset-password', ['email' => (string) $request->query('email')]);
    }

    public function reset(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email:rfc'],
            'code' => ['required', 'digits:6'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $email = Str::lower(trim($data['email']));

        // verify() throws a validation error on a wrong or expired code.
        $token = $this->otp->verify($email, $data['code'], VerificationCode::PURPOSE_PASSWORD_RESET);
        $this->otp->redeemToken($email, $token, VerificationCode::PURPOSE_PASSWORD_RESET, 'code');

        $user = User::where('email', $email)->firstOrFail();

        $user->update(['password' => $data['password']]);
        $user->tokens()->delete();

        ActivityLogger::user('user.password_reset', $user->id, ['via' => 'dashboard'], $user);

        return redirect()->route('login')->with('success', 'Password updated — you can sign in now.');
    }
}
