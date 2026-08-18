<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LeadUpdate extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'lead_id',
        'user_id',
        'from_status',
        'to_status',
        'outcome',
        'note',
        'next_action_at',
        'appointment_at',
        'expected_close_at',
        'meta',
        'created_at',
    ];

    protected $casts = [
        'next_action_at' => 'datetime',
        'appointment_at' => 'datetime',
        'expected_close_at' => 'datetime',
        'created_at' => 'datetime',
        'meta' => 'array',
    ];

    public function lead()
    {
        return $this->belongsTo(Lead::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
