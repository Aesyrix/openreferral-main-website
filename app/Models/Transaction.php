<?php

namespace App\Models;

use App\Enums\TransactionType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class Transaction extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'agent_id',
        'lead_id',
        'type',
        'amount_cents',
        'currency',
        'status',
        'reference',
        'created_at',
    ];

    protected $casts = [
        'type' => TransactionType::class,
        'amount_cents' => 'integer',
        'created_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $transaction) {
            $transaction->created_at ??= now();
        });
    }

    public function scopeCompleted(Builder $query): Builder
    {
        return $query->where('status', 'completed');
    }

    public function getAmountDollarsAttribute(): float
    {
        return round($this->amount_cents / 100, 2);
    }

    public function agent()
    {
        return $this->belongsTo(User::class, 'agent_id');
    }

    public function lead()
    {
        return $this->belongsTo(Lead::class, 'lead_id');
    }
}
