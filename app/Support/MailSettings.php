<?php

namespace App\Support;

use App\Models\Setting;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Crypt;
use Throwable;

/**
 * Email delivery configured from the admin dashboard instead of .env.
 *
 * Stored in `app_settings` (password encrypted at rest) and applied over the
 * mail config at boot, so a non-technical admin can point the system at their
 * SMTP provider without editing files or redeploying.
 *
 * `mail_transport`:
 *   env  — leave whatever .env / config/mail.php says (the default)
 *   smtp — use the SMTP credentials stored here
 *   log  — write emails to storage/logs/laravel.log, deliver nothing
 */
class MailSettings
{
    public const KEYS = [
        'mail_transport',
        'mail_host',
        'mail_port',
        'mail_username',
        'mail_password',
        'mail_encryption',
        'mail_from_address',
        'mail_from_name',
    ];

    /** Ready-made host/port/encryption for the providers people actually use. */
    public const PRESETS = [
        'gmail' => [
            'label' => 'Gmail / Google Workspace',
            'host' => 'smtp.gmail.com',
            'port' => 587,
            'encryption' => 'tls',
            'hint' => 'Username is your full Gmail address. The password must be a 16-character Google App Password, not your normal login password.',
        ],
        'outlook' => [
            'label' => 'Outlook / Microsoft 365',
            'host' => 'smtp.office365.com',
            'port' => 587,
            'encryption' => 'tls',
            'hint' => 'Username is your full Microsoft address. SMTP AUTH must be enabled for that mailbox in the Microsoft 365 admin centre.',
        ],
        'cpanel' => [
            'label' => 'cPanel / Hostinger / shared hosting',
            'host' => 'smtp.yourdomain.com',
            'port' => 465,
            'encryption' => 'ssl',
            'hint' => 'Use a mailbox created in your hosting panel. Some hosts list the server as mail.yourdomain.com — copy it exactly from their email settings page.',
        ],
        'sendgrid' => [
            'label' => 'SendGrid',
            'host' => 'smtp.sendgrid.net',
            'port' => 587,
            'encryption' => 'tls',
            'hint' => 'Username is the literal word apikey. The password is the API key you generated.',
        ],
        'mailgun' => [
            'label' => 'Mailgun',
            'host' => 'smtp.mailgun.org',
            'port' => 587,
            'encryption' => 'tls',
            'hint' => 'Use the SMTP username and password from your Mailgun domain, e.g. postmaster@mg.yourdomain.com.',
        ],
        'postmark' => [
            'label' => 'Postmark',
            'host' => 'smtp.postmarkapp.com',
            'port' => 587,
            'encryption' => 'tls',
            'hint' => 'Use your Server API token as both the username and the password.',
        ],
        'brevo' => [
            'label' => 'Brevo (Sendinblue)',
            'host' => 'smtp-relay.brevo.com',
            'port' => 587,
            'encryption' => 'tls',
            'hint' => 'Username is the login shown on the Brevo SMTP page; the password is the SMTP key, not your account password.',
        ],
        'custom' => [
            'label' => 'Other / custom SMTP',
            'host' => '',
            'port' => 587,
            'encryption' => 'tls',
            'hint' => 'Copy the outgoing (SMTP) server, port and encryption exactly as your provider lists them.',
        ],
    ];

    /**
     * The mailer config/mail.php booted with, captured before apply() mutates
     * it, so 'env' mode always reports the real .env value rather than whatever
     * a previous apply() left behind in this process.
     */
    private static ?string $baselineMailer = null;

    public static function baselineMailer(): string
    {
        return self::$baselineMailer ??= (string) config('mail.default', 'log');
    }

    /** Everything except the password, for rendering the form. */
    public static function all(): array
    {
        $stored = Setting::all_with_defaults();

        return [
            'mail_transport' => $stored['mail_transport'] ?? 'env',
            'mail_host' => $stored['mail_host'] ?? '',
            'mail_port' => $stored['mail_port'] ?? '587',
            'mail_username' => $stored['mail_username'] ?? '',
            'mail_encryption' => $stored['mail_encryption'] ?? 'tls',
            'mail_from_address' => $stored['mail_from_address'] ?? '',
            'mail_from_name' => $stored['mail_from_name'] ?? '',
        ];
    }

    public static function transport(): string
    {
        return (string) (Setting::get('mail_transport') ?: 'env');
    }

    public static function password(): string
    {
        $stored = (string) (Setting::get('mail_password') ?: '');

        if ($stored === '') {
            return '';
        }

        try {
            return Crypt::decryptString($stored);
        } catch (Throwable) {
            // APP_KEY changed since the password was saved — force a re-entry.
            return '';
        }
    }

    public static function hasPassword(): bool
    {
        return self::password() !== '';
    }

    /** True when emails will actually be delivered rather than written to the log. */
    public static function isLive(): bool
    {
        return self::effectiveMailer() !== 'log';
    }

    /** Whether the dashboard-managed SMTP block is filled in enough to use. */
    public static function smtpIsComplete(): bool
    {
        $settings = self::all();

        return filled($settings['mail_host'])
            && filled($settings['mail_port'])
            && filled($settings['mail_from_address']);
    }

    /** The mailer that will actually be used for the next send. */
    public static function effectiveMailer(): string
    {
        return match (self::transport()) {
            'smtp' => self::smtpIsComplete() ? 'smtp' : 'log',
            'log' => 'log',
            default => self::baselineMailer(),
        };
    }

    /**
     * Overlay the stored settings onto the mail config. Called once per boot
     * from AppServiceProvider, and again after a save so a test send in the
     * same request uses the new values.
     */
    public static function apply(): void
    {
        self::baselineMailer();

        try {
            $settings = self::all();
        } catch (Throwable) {
            // Database not migrated yet (fresh install, artisan migrate, ...).
            return;
        }

        // A "from" address is worth applying whichever transport is in use.
        if (filled($settings['mail_from_address'])) {
            Config::set('mail.from.address', $settings['mail_from_address']);
        }

        if (filled($settings['mail_from_name'])) {
            Config::set('mail.from.name', $settings['mail_from_name']);
        }

        $transport = $settings['mail_transport'];

        if ($transport === 'log') {
            Config::set('mail.default', 'log');

            return;
        }

        if ($transport !== 'smtp') {
            return; // 'env' — leave config/mail.php and .env alone.
        }

        if (! self::smtpIsComplete()) {
            // Half-configured SMTP would throw on every send. Fall back to the
            // log so the app keeps working; the dashboard shows the warning.
            Config::set('mail.default', 'log');

            return;
        }

        $encryption = $settings['mail_encryption'] ?: 'tls';

        Config::set('mail.default', 'smtp');
        Config::set('mail.mailers.smtp.transport', 'smtp');
        Config::set('mail.mailers.smtp.host', $settings['mail_host']);
        Config::set('mail.mailers.smtp.port', (int) $settings['mail_port']);
        Config::set('mail.mailers.smtp.username', $settings['mail_username'] ?: null);
        Config::set('mail.mailers.smtp.password', self::password() ?: null);
        Config::set('mail.mailers.smtp.encryption', $encryption === 'none' ? null : $encryption);

        // Laravel 11+ prefers `scheme`: smtps is TLS-on-connect (port 465),
        // smtp is plain or STARTTLS (port 587).
        Config::set('mail.mailers.smtp.scheme', $encryption === 'ssl' ? 'smtps' : 'smtp');
    }

    /** Persist the form, encrypting the password and keeping it if left blank. */
    public static function store(array $data): void
    {
        Setting::set('mail_transport', $data['mail_transport'] ?? 'env');
        Setting::set('mail_host', trim((string) ($data['mail_host'] ?? '')));
        Setting::set('mail_port', (string) ($data['mail_port'] ?? '587'));
        Setting::set('mail_username', trim((string) ($data['mail_username'] ?? '')));
        Setting::set('mail_encryption', $data['mail_encryption'] ?? 'tls');
        Setting::set('mail_from_address', trim((string) ($data['mail_from_address'] ?? '')));
        Setting::set('mail_from_name', trim((string) ($data['mail_from_name'] ?? '')));

        if (! empty($data['clear_password'])) {
            Setting::set('mail_password', '');
        } elseif (filled($data['mail_password'] ?? null)) {
            // Blank means "keep the saved password" — the form never echoes it back.
            Setting::set('mail_password', Crypt::encryptString((string) $data['mail_password']));
        }

        self::apply();
    }
}
