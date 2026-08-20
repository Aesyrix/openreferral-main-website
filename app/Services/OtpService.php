<?php

namespace App\Services;

use App\Exceptions\MailDeliveryException;
use App\Mail\OtpCodeMail;
use App\Models\Setting;
use App\Models\VerificationCode;
use App\Support\Mailer;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Email OTP for signup and password reset.
 *
 * Codes are 6 digits, hashed at rest, expire after `otp_ttl_minutes`, cap
 * attempts, and throttle resends. Verifying a code issues a single-use
 * short-lived token that the completing call must present.
 */
class OtpService
{
    public const TOKEN_TTL_MINUTES = 15;

    public function __construct(private Mailer $mailer) {}

    public function ttlMinutes(): int
    {
        return (int) Setting::get('otp_ttl_minutes', 10);
    }

    public function maxAttempts(): int
    {
        return (int) Setting::get('otp_max_attempts', 5);
    }

    public function resendSeconds(): int
    {
        return (int) Setting::get('otp_resend_seconds', 45);
    }

    /**
     * Issue and email a code. Returns the seconds until another resend is
     * allowed so the app can drive its countdown.
     */
    public function send(string $email, string $purpose, ?string $name = null, ?string $ip = null): array
    {
        $email = Str::lower(trim($email));

        $latest = VerificationCode::for($email, $purpose)->orderByDesc('id')->first();

        if ($latest && $latest->created_at && $latest->created_at->diffInSeconds(now()) < $this->resendSeconds()) {
            $wait = $this->resendSeconds() - $latest->created_at->diffInSeconds(now());

            throw ValidationException::withMessages([
                'email' => ["Please wait {$wait} seconds before requesting another code."],
            ])->status(429);
        }

        // Only one live code per email+purpose.
        VerificationCode::for($email, $purpose)->whereNull('consumed_at')->update(['consumed_at' => now()]);

        $code = (string) random_int(100000, 999999);

        $record = VerificationCode::create([
            'email' => $email,
            'code_hash' => Hash::make($code),
            'purpose' => $purpose,
            'expires_at' => now()->addMinutes($this->ttlMinutes()),
            'ip_address' => $ip,
            'created_at' => now(),
        ]);

        try {
            // Sent inline, not queued: the user is sitting on the OTP screen, and
            // a queue worker that isn't running would strand them there silently.
            $this->mailer->sendNow(
                $email,
                new OtpCodeMail($code, $purpose, $this->ttlMinutes(), $name),
                $purpose === VerificationCode::PURPOSE_SIGNUP ? 'otp_signup' : 'otp_password_reset'
            );
        } catch (\Throwable $e) {
            // Never leave a live code behind for an email that never went out,
            // and never report success for a send that failed.
            $record->update(['consumed_at' => now()]);

            throw new MailDeliveryException($e->getMessage(), $e);
        }

        return [
            'resend_in' => $this->resendSeconds(),
            'expires_in' => $this->ttlMinutes() * 60,
        ];
    }

    /**
     * Check a submitted code. On success returns a plaintext token bound to
     * this email+purpose; the code itself is consumed.
     */
    public function verify(string $email, string $code, string $purpose): string
    {
        $email = Str::lower(trim($email));

        $record = VerificationCode::for($email, $purpose)
            ->usable($this->maxAttempts())
            ->orderByDesc('id')
            ->first();

        if (! $record) {
            throw ValidationException::withMessages([
                'code' => ['That code has expired or is no longer valid. Request a new one.'],
            ]);
        }

        if (! Hash::check($code, $record->code_hash)) {
            $record->increment('attempts');

            $left = max($this->maxAttempts() - $record->attempts, 0);

            throw ValidationException::withMessages([
                'code' => [$left > 0
                    ? "Incorrect code. {$left} attempt(s) remaining."
                    : 'Too many incorrect attempts. Request a new code.'],
            ]);
        }

        $token = Str::random(64);

        $record->update([
            'consumed_at' => now(),
            'token_hash' => hash('sha256', $token),
            'token_expires_at' => now()->addMinutes(self::TOKEN_TTL_MINUTES),
        ]);

        return $token;
    }

    /**
     * Redeem the token issued by verify(). Single use: the token is cleared so
     * the same verification cannot complete twice.
     */
    public function redeemToken(string $email, string $token, string $purpose, string $field = 'verification_token'): void
    {
        $email = Str::lower(trim($email));

        $record = VerificationCode::for($email, $purpose)
            ->where('token_hash', hash('sha256', $token))
            ->orderByDesc('id')
            ->first();

        if (! $record || ! $record->tokenIsLive()) {
            throw ValidationException::withMessages([
                $field => ['This verification has expired. Please verify your email again.'],
            ]);
        }

        $record->update(['token_hash' => null, 'token_expires_at' => null]);
    }
}
