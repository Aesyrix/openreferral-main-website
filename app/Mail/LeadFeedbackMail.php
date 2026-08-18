<?php

namespace App\Mail;

use App\Models\Lead;
use App\Models\LeadFeedback;
use App\Models\User;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/** Events #4/#5/#6 — the agent's decision and comments, to staff + admin. */
class LeadFeedbackMail extends Mailable
{
    use SerializesModels;

    public function __construct(
        public Lead $lead,
        public LeadFeedback $feedback,
        public User $agent,
        public User $recipient,
    ) {}

    public function envelope(): Envelope
    {
        $verb = $this->feedback->accepted() ? 'accepted' : 'rejected';

        return new Envelope(
            subject: "{$this->agent->name} {$verb} lead {$this->lead->reference_id}",
        );
    }

    public function content(): Content
    {
        return new Content(view: 'emails.lead-feedback', with: [
            'leadUrl' => route('admin.leads.show', $this->lead->id),
        ]);
    }
}
