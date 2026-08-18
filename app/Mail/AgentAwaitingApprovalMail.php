<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/** Event #1 — new agent signed up, sent to every staff member and admin. */
class AgentAwaitingApprovalMail extends Mailable
{
    use SerializesModels;

    public function __construct(
        public User $agent,
        public User $recipient,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: "New agent awaiting approval: {$this->agent->name}");
    }

    public function content(): Content
    {
        return new Content(view: 'emails.agent-awaiting-approval', with: [
            'reviewUrl' => route('admin.agents.show', $this->agent->id),
        ]);
    }
}
