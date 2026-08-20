<?php

namespace App\Services;

use App\Models\Device;
use App\Support\PushSettings;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Firebase Cloud Messaging sender (Android + iOS via APNs).
 *
 * Supports the HTTP v1 API (service-account JSON, preferred) and the legacy
 * server-key API. With neither configured it logs what it would have sent, so
 * local development and tests run without Firebase credentials.
 */
class FcmService
{
    private const V1_ENDPOINT = 'https://fcm.googleapis.com/v1/projects/%s/messages:send';

    private const LEGACY_ENDPOINT = 'https://fcm.googleapis.com/fcm/send';

    private const OAUTH_ENDPOINT = 'https://oauth2.googleapis.com/token';

    private const SCOPE = 'https://www.googleapis.com/auth/firebase.messaging';

    public function isConfigured(): bool
    {
        return $this->serviceAccount() !== null || filled($this->serverKey());
    }

    /** Dashboard-stored key first, then the env fallback. */
    private function serverKey(): ?string
    {
        return PushSettings::serverKey() ?: config('services.fcm.server_key');
    }

    /**
     * Push to every registered device of the given users.
     *
     * @param  \Illuminate\Support\Collection<int,\App\Models\User>|array  $users
     * @param  array<string,string|int|null>  $data  deep-link payload (lead_id, type, ...)
     * @return int number of tokens accepted
     */
    public function pushToUsers($users, string $title, string $body, array $data = []): int
    {
        $userIds = collect($users)->pluck('id')->filter()->all();

        if (empty($userIds)) {
            return 0;
        }

        $tokens = Device::whereIn('user_id', $userIds)->pluck('token')->all();

        return $this->pushToTokens($tokens, $title, $body, $data);
    }

    /**
     * @param  array<int,string>  $tokens
     * @param  array<string,string|int|null>  $data
     */
    public function pushToTokens(array $tokens, string $title, string $body, array $data = []): int
    {
        $tokens = array_values(array_unique(array_filter($tokens)));

        if (empty($tokens)) {
            return 0;
        }

        // FCM data payloads must be string=>string.
        $data = collect($data)
            ->filter(fn ($value) => $value !== null)
            ->map(fn ($value) => (string) $value)
            ->all();

        if (! $this->isConfigured()) {
            Log::info('[fcm] not configured — push skipped', compact('title', 'body', 'data') + ['tokens' => count($tokens)]);

            return 0;
        }

        return $this->serviceAccount() !== null
            ? $this->sendV1($tokens, $title, $body, $data)
            : $this->sendLegacy($tokens, $title, $body, $data);
    }

    // ------------------------------------------------------------- HTTP v1

    private function sendV1(array $tokens, string $title, string $body, array $data): int
    {
        $accessToken = $this->accessToken();

        if (! $accessToken) {
            return 0;
        }

        $url = sprintf(self::V1_ENDPOINT, $this->serviceAccount()['project_id']);
        $sent = 0;

        // The v1 API is one-token-per-request; batch sends are not available.
        foreach ($tokens as $token) {
            try {
                $response = Http::withToken($accessToken)
                    ->timeout(10)
                    ->post($url, [
                        'message' => [
                            'token' => $token,
                            'notification' => ['title' => $title, 'body' => $body],
                            'data' => $data,
                            'android' => [
                                'priority' => 'high',
                                'notification' => ['sound' => 'default', 'channel_id' => 'open_referral_leads'],
                            ],
                            'apns' => [
                                'payload' => ['aps' => ['sound' => 'default', 'badge' => 1]],
                            ],
                        ],
                    ]);

                if ($response->successful()) {
                    $sent++;

                    continue;
                }

                $status = $response->json('error.status');

                if (in_array($status, ['NOT_FOUND', 'UNREGISTERED', 'INVALID_ARGUMENT'], true)) {
                    $this->forgetToken($token);
                } else {
                    Log::warning('[fcm] v1 send failed', ['status' => $status, 'body' => $response->body()]);
                }
            } catch (\Throwable $e) {
                Log::warning('[fcm] v1 transport error', ['error' => $e->getMessage()]);
            }
        }

        return $sent;
    }

    /** OAuth2 access token from the service account, cached just under its hour. */
    private function accessToken(): ?string
    {
        return Cache::remember('fcm_access_token', 3300, function () {
            $account = $this->serviceAccount();
            $now = time();

            $jwt = $this->signJwt([
                'iss' => $account['client_email'],
                'scope' => self::SCOPE,
                'aud' => self::OAUTH_ENDPOINT,
                'iat' => $now,
                'exp' => $now + 3600,
            ], $account['private_key']);

            if (! $jwt) {
                return null;
            }

            try {
                $response = Http::asForm()->timeout(10)->post(self::OAUTH_ENDPOINT, [
                    'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                    'assertion' => $jwt,
                ]);

                if (! $response->successful()) {
                    Log::error('[fcm] token exchange failed', ['body' => $response->body()]);

                    return null;
                }

                return $response->json('access_token');
            } catch (\Throwable $e) {
                Log::error('[fcm] token exchange error', ['error' => $e->getMessage()]);

                return null;
            }
        });
    }

    private function signJwt(array $claims, string $privateKey): ?string
    {
        $encode = fn (array $part) => rtrim(strtr(base64_encode(json_encode($part)), '+/', '-_'), '=');

        $payload = $encode(['alg' => 'RS256', 'typ' => 'JWT']).'.'.$encode($claims);

        $key = openssl_pkey_get_private($privateKey);

        if (! $key || ! openssl_sign($payload, $signature, $key, OPENSSL_ALGO_SHA256)) {
            Log::error('[fcm] could not sign service-account JWT');

            return null;
        }

        return $payload.'.'.rtrim(strtr(base64_encode($signature), '+/', '-_'), '=');
    }

    /** @return array{project_id:string,client_email:string,private_key:string}|null */
    private function serviceAccount(): ?array
    {
        // Credentials pasted into the admin dashboard win over the env file.
        if ($fromSettings = PushSettings::serviceAccount()) {
            return $fromSettings;
        }

        $path = config('services.fcm.credentials');

        if (blank($path)) {
            return null;
        }

        return Cache::remember('fcm_service_account', 3600, function () use ($path) {
            $file = str_starts_with($path, '/') || preg_match('/^[A-Za-z]:/', $path)
                ? $path
                : base_path($path);

            if (! is_readable($file)) {
                Log::error('[fcm] service account file unreadable', ['path' => $file]);

                return null;
            }

            $json = json_decode((string) file_get_contents($file), true);

            if (! isset($json['project_id'], $json['client_email'], $json['private_key'])) {
                Log::error('[fcm] service account file is missing required keys');

                return null;
            }

            return [
                'project_id' => $json['project_id'],
                'client_email' => $json['client_email'],
                'private_key' => $json['private_key'],
            ];
        });
    }

    // ------------------------------------------------------------ Legacy API

    private function sendLegacy(array $tokens, string $title, string $body, array $data): int
    {
        $sent = 0;

        foreach (array_chunk($tokens, 500) as $chunk) {
            try {
                $response = Http::withHeaders([
                    'Authorization' => 'key='.$this->serverKey(),
                ])->timeout(10)->post(self::LEGACY_ENDPOINT, [
                    'registration_ids' => $chunk,
                    'priority' => 'high',
                    'notification' => ['title' => $title, 'body' => $body, 'sound' => 'default'],
                    'data' => $data,
                ]);

                if (! $response->successful()) {
                    Log::warning('[fcm] legacy send failed', ['body' => $response->body()]);

                    continue;
                }

                $sent += (int) $response->json('success', 0);

                foreach ((array) $response->json('results', []) as $i => $result) {
                    if (in_array($result['error'] ?? null, ['NotRegistered', 'InvalidRegistration'], true)) {
                        $this->forgetToken($chunk[$i]);
                    }
                }
            } catch (\Throwable $e) {
                Log::warning('[fcm] legacy transport error', ['error' => $e->getMessage()]);
            }
        }

        return $sent;
    }

    private function forgetToken(string $token): void
    {
        Device::where('token', $token)->delete();
    }
}
