<?php

namespace App\Models;

use App\Enums\LeadStatus;
use App\Models\Concerns\SetsCreatedAt;
use Illuminate\Database\Eloquent\Model;

class LeadStatusHistory extends Model
{
    use SetsCreatedAt;

    public $timestamps = false;

    protected $table = 'lead_status_history';

    protected $fillable = [
        'lead_id',
        'from_status',
        'to_status',
        'changed_by',
        'created_at',
    ];

    protected $casts = [
        'created_at' => 'datetime',
    ];

    public function fromLabel(): ?string
    {
        return $this->from_status ? LeadStatus::tryFrom($this->from_status)?->label() : null;
    }

    public function toLabel(): string
    {
        return LeadStatus::tryFrom($this->to_status)?->label() ?? $this->to_status;
    }

    public function lead()
    {
        return $this->belongsTo(Lead::class, 'lead_id');
    }

    public function changedBy()
    {
        return $this->belongsTo(User::class, 'changed_by');
    }
}
