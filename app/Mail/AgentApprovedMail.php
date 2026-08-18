<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/** Event #2 — the agent's account was approved. */
class AgentApprovedMail extends Mailable
{
    use SerializesModels;

    public function __construct(public User $agent) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: "You're approved — start receiving Open Referral leads");
    }

    public function content(): Content
    {
        return new Content(view: 'emails.agent-approved');
    }
}
