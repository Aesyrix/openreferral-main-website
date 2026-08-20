<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/** Sent by the admin settings page to prove the mail credentials work. */
class TestMail extends Mailable
{
    use SerializesModels;

    public function __construct(public User $recipient) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Open Referral — email delivery test');
    }

    public function content(): Content
    {
        return new Content(view: 'emails.test', with: [
            'sentAt' => now()->format('M j, Y g:i A').' UTC',
        ]);
    }
}
