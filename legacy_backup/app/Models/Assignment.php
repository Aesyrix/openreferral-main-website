<?php

namespace App\Models;

use App\Enums\AssignmentStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Assignment extends Model
{
    use HasFactory;

    protected $fillable = [
        'lead_id',
        'agent_id',
        'assigned_by_id',
        'status',
        'reason',
        'note',
        'sla_due_at',
        'assigned_at',
        'responded_at',
        'accept_by_at',
    ];

    protected $casts = [
        'status' => AssignmentStatus::class,
        'assigned_at' => 'datetime',
        'responded_at' => 'datetime',
        'accept_by_at' => 'datetime',
        'sla_due_at' => 'datetime',
    ];

    public function lead()
    {
        return $this->belongsTo(Lead::class);
    }

    public function agent()
    {
        return $this->belongsTo(User::class, 'agent_id');
    }

    public function assignedBy()
    {
        return $this->belongsTo(User::class, 'assigned_by_id');
    }
}
