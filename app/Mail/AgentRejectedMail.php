<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/** Event #2 — the agent's application was not approved. */
class AgentRejectedMail extends Mailable
{
    use SerializesModels;

    public function __construct(
        public User $agent,
        public ?string $reason = null,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Your Open Referral application status');
    }

    public function content(): Content
    {
        return new Content(view: 'emails.agent-rejected');
    }
}
