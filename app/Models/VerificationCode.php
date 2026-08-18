<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class VerificationCode extends Model
{
    public const PURPOSE_SIGNUP = 'signup';
    public const PURPOSE_PASSWORD_RESET = 'password_reset';

    public $timestamps = false;

    protected $table = 'verification_codes';

    protected $fillable = [
        'email',
        'code_hash',
        'token_hash',
        'purpose',
        'attempts',
        'consumed_at',
        'expires_at',
        'token_expires_at',
        'ip_address',
        'created_at',
    ];

    protected $casts = [
        'attempts' => 'integer',
        'consumed_at' => 'datetime',
        'expires_at' => 'datetime',
        'token_expires_at' => 'datetime',
        'created_at' => 'datetime',
    ];

    public function scopeFor(Builder $query, string $email, string $purpose): Builder
    {
        return $query->where('email', $email)->where('purpose', $purpose);
    }

    /** Codes that can still be entered: not used, not expired, attempts left. */
    public function scopeUsable(Builder $query, int $maxAttempts): Builder
    {
        return $query->whereNull('consumed_at')
            ->where('expires_at', '>', now())
            ->where('attempts', '<', $maxAttempts);
    }

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }

    public function tokenIsLive(): bool
    {
        return $this->token_hash !== null
            && $this->token_expires_at !== null
            && $this->token_expires_at->isFuture();
    }
}
