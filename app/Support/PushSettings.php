<?php

namespace App\Support;

use App\Models\Setting;
use Illuminate\Support\Facades\Crypt;
use Throwable;

/**
 * Firebase credentials pasted into the admin dashboard rather than deployed as
 * a file — the same reasoning as MailSettings, since shared hosting makes
 * dropping a JSON key file and editing .env awkward.
 *
 * The service-account JSON is stored encrypted in `app_settings`. FcmService
 * prefers what is stored here and falls back to the FCM_CREDENTIALS /
 * FCM_SERVER_KEY env values.
 */
class PushSettings
{
    public const KEY_SERVICE_ACCOUNT = 'fcm_service_account';

    public const KEY_SERVER_KEY = 'fcm_server_key';

    /** @return array{project_id:string,client_email:string,private_key:string}|null */
    public static function serviceAccount(): ?array
    {
        $stored = (string) (Setting::get(self::KEY_SERVICE_ACCOUNT) ?: '');

        if ($stored === '') {
            return null;
        }

        try {
            $json = json_decode(Crypt::decryptString($stored), true);
        } catch (Throwable) {
            return null; // APP_KEY rotated — the admin must paste it again.
        }

        if (! isset($json['project_id'], $json['client_email'], $json['private_key'])) {
            return null;
        }

        return [
            'project_id' => $json['project_id'],
            'client_email' => $json['client_email'],
            'private_key' => $json['private_key'],
        ];
    }

    public static function serverKey(): ?string
    {
        $stored = (string) (Setting::get(self::KEY_SERVER_KEY) ?: '');

        if ($stored === '') {
            return null;
        }

        try {
            return Crypt::decryptString($stored);
        } catch (Throwable) {
            return null;
        }
    }

    public static function projectId(): ?string
    {
        return self::serviceAccount()['project_id'] ?? null;
    }

    public static function isConfigured(): bool
    {
        return self::serviceAccount() !== null || filled(self::serverKey());
    }

    /**
     * Validate and store a pasted service-account JSON.
     *
     * @return string|null an error message, or null on success
     */
    public static function storeServiceAccount(string $json): ?string
    {
        $json = trim($json);

        if ($json === '') {
            Setting::set(self::KEY_SERVICE_ACCOUNT, '');

            return null;
        }

        $decoded = json_decode($json, true);

        if (! is_array($decoded)) {
            return 'That is not valid JSON. Paste the whole file, including the outer { } braces.';
        }

        foreach (['project_id', 'client_email', 'private_key'] as $required) {
            if (empty($decoded[$required])) {
                return "The JSON is missing \"{$required}\". Make sure you downloaded a service account key, not the google-services.json meant for the Android app.";
            }
        }

        if (! str_contains($decoded['private_key'], 'PRIVATE KEY')) {
            return 'The "private_key" value looks wrong — it should contain a BEGIN PRIVATE KEY block.';
        }

        Setting::set(self::KEY_SERVICE_ACCOUNT, Crypt::encryptString($json));

        // The v1 API supersedes the legacy key; drop it so there is one source.
        Setting::set(self::KEY_SERVER_KEY, '');

        return null;
    }

    public static function storeServerKey(string $key): void
    {
        $key = trim($key);

        Setting::set(self::KEY_SERVER_KEY, $key === '' ? '' : Crypt::encryptString($key));
    }

    public static function clear(): void
    {
        Setting::set(self::KEY_SERVICE_ACCOUNT, '');
        Setting::set(self::KEY_SERVER_KEY, '');
    }
}
