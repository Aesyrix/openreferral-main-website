<?php

namespace App\Models;

use App\Enums\Role;
use App\Enums\UserStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, SoftDeletes;

    protected $fillable = [
        'role',
        'name',
        'email',
        'email_verified_at',
        'password',
        'phone',
        'avatar_url',
        'status',
        'created_by',
        'last_login_at',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'role' => Role::class,
        'status' => UserStatus::class,
        'email_verified_at' => 'datetime',
        'last_login_at' => 'datetime',
        'password' => 'hashed',
    ];

    // ---------------------------------------------------------------- Scopes

    public function scopeAgents(Builder $query): Builder
    {
        return $query->where('role', Role::AGENT);
    }

    public function scopeStaffAndAdmins(Builder $query): Builder
    {
        return $query->whereIn('role', [Role::STAFF, Role::ADMIN]);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', UserStatus::ACTIVE);
    }

    // ------------------------------------------------------------- Role tests

    public function isAdmin(): bool
    {
        return $this->role === Role::ADMIN;
    }

    /** True for admins too — an admin passes every staff gate. */
    public function isStaff(): bool
    {
        return $this->role === Role::STAFF || $this->role === Role::ADMIN;
    }

    public function isOnlyStaff(): bool
    {
        return $this->role === Role::STAFF;
    }

    public function isAgent(): bool
    {
        return $this->role === Role::AGENT;
    }

    public function isActive(): bool
    {
        return $this->status === UserStatus::ACTIVE;
    }

    public function isPending(): bool
    {
        return $this->status === UserStatus::PENDING;
    }

    // ---------------------------------------------------------- Relationships

    public function agentProfile()
    {
        return $this->hasOne(AgentProfile::class, 'user_id');
    }

    public function serviceAreas()
    {
        return $this->hasMany(AgentServiceArea::class, 'user_id');
    }

    public function settings()
    {
        return $this->hasOne(UserSetting::class, 'user_id');
    }

    public function assignedLeads()
    {
        return $this->hasMany(Lead::class, 'assigned_to');
    }

    public function createdLeads()
    {
        return $this->hasMany(Lead::class, 'created_by');
    }

    public function devices()
    {
        return $this->hasMany(Device::class, 'user_id');
    }

    public function notifications()
    {
        return $this->hasMany(Notification::class, 'user_id')->latest('created_at');
    }

    public function unreadNotifications()
    {
        return $this->notifications()->whereNull('read_at');
    }

    public function feedback()
    {
        return $this->hasMany(LeadFeedback::class, 'agent_id');
    }

    public function transactions()
    {
        return $this->hasMany(Transaction::class, 'agent_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    // -------------------------------------------------------------- Accessors

    /** Preferences always resolve, even before a settings row exists. */
    public function settingsOrDefault(): UserSetting
    {
        return $this->settings ?? UserSetting::firstOrCreate(['user_id' => $this->id]);
    }

    public function wantsPush(): bool
    {
        return (bool) $this->settingsOrDefault()->push_notifications;
    }

    public function wantsEmail(): bool
    {
        return (bool) $this->settingsOrDefault()->email_notifications;
    }

    public function getAvatarUrlAttribute($value): string
    {
        if ($value) {
            return str_starts_with($value, 'http')
                ? $value
                : Storage::disk('public')->url($value);
        }

        return 'https://ui-avatars.com/api/?name='.urlencode($this->name).'&background=1B86E0&color=ffffff';
    }

    /** The raw stored path/URL, bypassing the placeholder fallback. */
    public function rawAvatar(): ?string
    {
        return $this->attributes['avatar_url'] ?? null;
    }

    public function getIsVerifiedAttribute(): bool
    {
        return (bool) ($this->agentProfile?->verified ?? false);
    }
}
