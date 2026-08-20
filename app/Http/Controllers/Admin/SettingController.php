<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Mail\TestMail;
use App\Models\EmailLog;
use App\Models\Setting;
use App\Services\FcmService;
use App\Support\ActivityLogger;
use App\Support\MailSettings;
use App\Support\PushSettings;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Throwable;

/** Operational settings, email delivery setup, and delivery health — admin only. */
class SettingController extends Controller
{
    public function index(FcmService $fcm): View
    {
        $this->authorize('manage-settings');

        return view('admin.settings.index', [
            'settings' => Setting::all_with_defaults(),
            'pushProjectId' => PushSettings::projectId(),
            'pushFromDashboard' => PushSettings::isConfigured(),
            'storage' => $this->storageHealth(),
            'mail' => MailSettings::all(),
            'mailPresets' => MailSettings::PRESETS,
            'mailHasPassword' => MailSettings::hasPassword(),
            'mailIsLive' => MailSettings::isLive(),
            'effectiveMailer' => MailSettings::effectiveMailer(),
            'envMailer' => config('mail.default'),
            'pushConfigured' => $fcm->isConfigured(),
            'queue' => config('queue.default'),
            'emailStats' => EmailLog::query()
                ->groupBy('status')
                ->selectRaw('status, COUNT(*) AS aggregate')
                ->pluck('aggregate', 'status'),
            'recentEmails' => EmailLog::orderByDesc('id')->take(25)->get(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $this->authorize('manage-settings');

        $data = $request->validate([
            'company_name' => ['required', 'string', 'max:120'],
            'support_email' => ['required', 'email:rfc', 'max:190'],
            'default_lead_fee_dollars' => ['required', 'numeric', 'min:0', 'max:1000000'],
            'otp_ttl_minutes' => ['required', 'integer', 'min:2', 'max:60'],
            'otp_max_attempts' => ['required', 'integer', 'min:3', 'max:10'],
            'otp_resend_seconds' => ['required', 'integer', 'min:15', 'max:600'],
        ]);

        foreach ($data as $key => $value) {
            Setting::set($key, $value);
        }

        ActivityLogger::log('settings.updated', null, 'settings', null, array_keys($data));

        return back()->with('success', 'Settings saved.');
    }

    /** Email delivery: transport choice plus SMTP credentials. */
    public function updateMail(Request $request): RedirectResponse
    {
        $this->authorize('manage-settings');

        $data = $request->validate([
            'mail_transport' => ['required', Rule::in(['env', 'smtp', 'log'])],
            'mail_host' => ['nullable', 'string', 'max:190', 'required_if:mail_transport,smtp'],
            'mail_port' => ['nullable', 'integer', 'min:1', 'max:65535', 'required_if:mail_transport,smtp'],
            'mail_username' => ['nullable', 'string', 'max:190'],
            'mail_password' => ['nullable', 'string', 'max:190'],
            'mail_encryption' => ['required', Rule::in(['tls', 'ssl', 'none'])],
            'mail_from_address' => ['nullable', 'email:rfc', 'max:190', 'required_if:mail_transport,smtp'],
            'mail_from_name' => ['nullable', 'string', 'max:120'],
            'clear_password' => ['nullable', 'boolean'],
        ], [
            'mail_host.required_if' => 'The SMTP server is required when sending over SMTP.',
            'mail_port.required_if' => 'The SMTP port is required when sending over SMTP.',
            'mail_from_address.required_if' => 'A "from" address is required when sending over SMTP.',
        ]);

        MailSettings::store($data);

        // Long-running queue workers hold the old config in memory.
        try {
            Artisan::call('queue:restart');
        } catch (Throwable) {
            // Not fatal — the worker picks it up on its next manual restart.
        }

        ActivityLogger::log('settings.mail_updated', null, 'settings', null, [
            'transport' => $data['mail_transport'],
            'host' => $data['mail_host'] ?? null,
        ]);

        $message = match (MailSettings::effectiveMailer()) {
            'smtp' => 'Email settings saved. Send a test message below to confirm they work.',
            'log' => 'Saved. Emails are being written to the log, not delivered.',
            default => 'Email settings saved.',
        };

        return back()->with('success', $message)->withFragment('email-delivery');
    }

    /**
     * Send a real message right now, outside the queue, and report the actual
     * SMTP error back to the admin instead of burying it in a log file.
     */
    public function sendTestEmail(Request $request): RedirectResponse
    {
        $this->authorize('manage-settings');

        $data = $request->validate([
            'test_email' => ['required', 'email:rfc', 'max:190'],
        ]);

        $admin = $request->user();

        $log = EmailLog::create([
            'user_id' => $admin->id,
            'to_email' => $data['test_email'],
            'event' => 'test',
            'subject' => 'Open Referral — email delivery test',
            'status' => 'queued',
            'created_at' => now(),
        ]);

        try {
            Mail::to($data['test_email'])->send(new TestMail($admin));
        } catch (Throwable $e) {
            $log->update(['status' => 'failed', 'error' => Str::limit($e->getMessage(), 495)]);

            return back()
                ->with('error', 'Test email failed: '.Str::limit($e->getMessage(), 300))
                ->withFragment('email-delivery');
        }

        $log->update(['status' => 'sent', 'sent_at' => now(), 'error' => null]);

        $message = MailSettings::isLive()
            ? "Test email sent to {$data['test_email']}. Check the inbox (and the spam folder)."
            : "Test email written to storage/logs/laravel.log — delivery is still switched off, so nothing reached {$data['test_email']}.";

        return back()->with('success', $message)->withFragment('email-delivery');
    }

    /** Firebase credentials for push, pasted rather than deployed as a file. */
    public function updatePush(Request $request): RedirectResponse
    {
        $this->authorize('manage-settings');

        $data = $request->validate([
            'service_account_json' => ['nullable', 'string', 'max:20000'],
            'server_key' => ['nullable', 'string', 'max:500'],
            'clear' => ['nullable', 'boolean'],
        ]);

        if (! empty($data['clear'])) {
            PushSettings::clear();
            ActivityLogger::log('settings.push_cleared', null, 'settings');

            return back()->with('success', 'Firebase credentials removed. Push is off.')->withFragment('push');
        }

        if (filled($data['service_account_json'] ?? null)) {
            if ($error = PushSettings::storeServiceAccount($data['service_account_json'])) {
                return back()->with('error', $error)->withFragment('push');
            }
        } elseif (filled($data['server_key'] ?? null)) {
            PushSettings::storeServerKey($data['server_key']);
        } else {
            return back()->with('error', 'Paste either the service-account JSON or a legacy server key.')->withFragment('push');
        }

        ActivityLogger::log('settings.push_updated', null, 'settings', null, [
            'project_id' => PushSettings::projectId(),
        ]);

        return back()
            ->with('success', 'Firebase credentials saved. Push notifications are now enabled.')
            ->withFragment('push');
    }

    /**
     * Is the public disk actually reachable over HTTP? Avatars 404 when the
     * `public/storage` symlink is missing, which is easy to miss on a server.
     */
    private function storageHealth(): array
    {
        $linkPath = public_path('storage');
        $linkExists = file_exists($linkPath);
        $avatarDir = storage_path('app/public/avatars');

        return [
            'link_exists' => $linkExists,
            'link_path' => $linkPath,
            'served_by_php' => (bool) config('filesystems.disks.public.serve'),
            'writable' => is_writable(storage_path('app/public')),
            'avatar_count' => is_dir($avatarDir) ? count(glob($avatarDir.'/*/*') ?: []) : 0,
            'base_url' => rtrim((string) config('filesystems.disks.public.url'), '/'),
        ];
    }
}
