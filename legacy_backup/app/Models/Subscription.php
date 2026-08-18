<?php

namespace App\Models;

use App\Enums\SubscriptionStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Subscription extends Model
{
    use HasFactory;

    protected $fillable = [
        'uuid',
        'agent_id',
        'package_id',
        'status',
        'starts_at',
        'ends_at',
        'auto_renew',
        'billing_cycle',
        'price_cents_at_purchase',
        'next_billing_at',
        'cancelled_at',
        'cancellation_reason',
        'renewed_from_subscription_id',
    ];

    protected $casts = [
        'status' => SubscriptionStatus::class,
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'next_billing_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'auto_renew' => 'boolean',
    ];

    protected static function boot()
    {
        parent::boot();
        static::creating(function ($model) {
            if (empty($model->uuid)) {
                $model->uuid = (string) Str::uuid();
            }
        });
    }

    public function agent()
    {
        return $this->belongsTo(User::class, 'agent_id');
    }

    public function package()
    {
        return $this->belongsTo(Package::class);
    }
}
