<?php

namespace App\Models;

use App\Enums\RejectionReason;
use Illuminate\Database\Eloquent\Model;

class LeadFeedback extends Model
{
    public $timestamps = false;

    protected $table = 'lead_feedback';

    protected $fillable = [
        'lead_id',
        'agent_id',
        'decision',
        'reason',
        'comments',
        'created_at',
    ];

    protected $casts = [
        'created_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $feedback) {
            $feedback->created_at ??= now();
        });
    }

    public function reasonLabel(): ?string
    {
        if (! $this->reason) {
            return null;
        }

        return RejectionReason::tryFrom($this->reason)?->label() ?? $this->reason;
    }

    public function accepted(): bool
    {
        return $this->decision === 'accept';
    }

    public function lead()
    {
        return $this->belongsTo(Lead::class, 'lead_id');
    }

    public function agent()
    {
        return $this->belongsTo(User::class, 'agent_id');
    }
}
