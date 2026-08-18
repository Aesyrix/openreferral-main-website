<?php

namespace App\Mail;

use App\Models\VerificationCode;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class OtpCodeMail extends Mailable
{
    use SerializesModels;

    public function __construct(
        public string $code,
        public string $purpose,
        public int $ttlMinutes,
        public ?string $name = null,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->purpose === VerificationCode::PURPOSE_SIGNUP
                ? "Your Open Referral verification code: {$this->code}"
                : "Your Open Referral password reset code: {$this->code}",
        );
    }

    public function content(): Content
    {
        return new Content(view: 'emails.otp-code', with: [
            'isSignup' => $this->purpose === VerificationCode::PURPOSE_SIGNUP,
        ]);
    }
}
