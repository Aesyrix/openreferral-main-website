<?php

namespace App\Support;

use App\Jobs\SendLoggedEmail;
use App\Models\EmailLog;
use App\Models\User;
use Illuminate\Mail\Mailable;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Throwable;

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

    /**
     * Send immediately, in-process, instead of via the queue.
     *
     * For time-critical mail — signup and password-reset codes — where a queue
     * worker that is not running would silently strand the user on the OTP
     * screen. Costs a second or two on the request; worth it for the one email
     * the whole signup flow depends on.
     */
    public function sendNow(string $toEmail, Mailable $mailable, string $event, ?User $user = null): EmailLog
    {
        $log = EmailLog::create([
            'user_id' => $user?->id,
            'to_email' => $toEmail,
            'event' => $event,
            'subject' => $this->subjectOf($mailable),
            'status' => 'queued',
            'created_at' => now(),
        ]);

        try {
            Mail::to($toEmail)->send($mailable);
        } catch (Throwable $e) {
            $log->update(['status' => 'failed', 'error' => Str::limit($e->getMessage(), 495)]);

            report($e);

            throw $e;
        }

        $log->update(['status' => 'sent', 'sent_at' => now(), 'error' => null]);

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

        return Str::limit($subject ?? class_basename($mailable), 185, '');
    }
}
