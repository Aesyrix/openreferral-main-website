<?php

namespace App\Jobs;

use App\Models\EmailLog;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Throwable;

class SendLoggedEmail implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public array $backoff = [10, 60];

    public function __construct(
        public int $emailLogId,
        public string $toEmail,
        public Mailable $mailable,
    ) {}

    public function handle(): void
    {
        Mail::to($this->toEmail)->send($this->mailable);

        EmailLog::whereKey($this->emailLogId)->update([
            'status' => 'sent',
            'sent_at' => now(),
            'error' => null,
        ]);
    }

    public function failed(?Throwable $e): void
    {
        EmailLog::whereKey($this->emailLogId)->update([
            'status' => 'failed',
            'error' => Str::limit($e?->getMessage() ?? 'Unknown mail failure', 495),
        ]);
    }
}
