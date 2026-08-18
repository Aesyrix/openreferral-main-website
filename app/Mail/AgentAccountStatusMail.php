<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/** Event #8 — agent suspended, reactivated, or removed. */
class AgentAccountStatusMail extends Mailable
{
    use SerializesModels;

    public function __construct(
        public User $agent,
        public string $state,
        public ?string $reason = null,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: match ($this->state) {
            'suspended' => 'Your Open Referral account has been suspended',
            'restored' => 'Your Open Referral account has been reactivated',
            default => 'Your Open Referral account has been removed',
        });
    }

    public function content(): Content
    {
        return new Content(view: 'emails.agent-account-status');
    }
}
