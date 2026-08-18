<?php

namespace App\Support;

use App\Jobs\SendLoggedEmail;
use App\Models\EmailLog;
use App\Models\User;
use Illuminate\Mail\Mailable;

/**
 * Every outbound email goes through here so it lands in `email_log` (queued →
 * sent/failed) for deliverability debugging, and is sent off the request cycle.
 */
class Mailer
{
    public function send(string $toEmail, Mailable $mailable, string $event, ?User $user = null): EmailLog
    {
        $log = EmailLog::create([
            'user_id' => $user?->id,
            'to_email' => $toEmail,
            'event' => $event,
            'subject' => $this->subjectOf($mailable),
            'status' => 'queued',
            'created_at' => now(),
        ]);

        SendLoggedEmail::dispatch($log->id, $toEmail, $mailable);

        return $log;
    }

    /** Send to a user, honouring their email_notifications preference. */
    public function sendToUser(User $user, Mailable $mailable, string $event, bool $force = false): ?EmailLog
    {
        if (! $force && ! $user->wantsEmail()) {
            return null;
        }

        return $this->send($user->email, $mailable, $event, $user);
    }

    private function subjectOf(Mailable $mailable): string
    {
        $subject = method_exists($mailable, 'envelope')
            ? $mailable->envelope()->subject
            : null;

        return \Illuminate\Support\Str::limit($subject ?? class_basename($mailable), 185, '');
    }
}
