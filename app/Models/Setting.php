<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class Setting extends Model
{
    public $timestamps = false;
    public $incrementing = false;

    protected $table = 'app_settings';
    protected $primaryKey = 'key';
    protected $keyType = 'string';

    protected $fillable = ['key', 'value', 'updated_at'];

    public const DEFAULTS = [
        'company_name' => 'Open Referral Network',
        'support_email' => 'support@openreferral.us',
        'default_lead_fee_dollars' => '250',
        'otp_ttl_minutes' => '10',
        'otp_max_attempts' => '5',
        'otp_resend_seconds' => '45',

        // Email delivery — see App\Support\MailSettings. 'env' keeps whatever
        // .env says; an admin switches this to 'smtp' from the settings page.
        'mail_transport' => 'env',
        'mail_host' => '',
        'mail_port' => '587',
        'mail_username' => '',
        'mail_password' => '',
        'mail_encryption' => 'tls',
        'mail_from_address' => '',
        'mail_from_name' => '',
    ];

    public static function get(string $key, mixed $default = null): mixed
    {
        $all = Cache::remember('app_settings', 300, fn () => static::pluck('value', 'key')->all());

        return $all[$key] ?? $default ?? (self::DEFAULTS[$key] ?? null);
    }

    public static function set(string $key, mixed $value): void
    {
        static::updateOrCreate(
            ['key' => $key],
            ['value' => is_scalar($value) ? (string) $value : json_encode($value), 'updated_at' => now()]
        );

        Cache::forget('app_settings');
    }

    public static function all_with_defaults(): array
    {
        $stored = Cache::remember('app_settings', 300, fn () => static::pluck('value', 'key')->all());

        return array_merge(self::DEFAULTS, $stored);
    }
}
