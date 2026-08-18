<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/** Admin created a staff account — send the invite with a temporary password. */
class StaffInviteMail extends Mailable
{
    use SerializesModels;

    public function __construct(
        public User $staff,
        public string $temporaryPassword,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Your Open Referral dashboard access');
    }

    public function content(): Content
    {
        return new Content(view: 'emails.staff-invite', with: [
            'loginUrl' => route('login'),
        ]);
    }
}
