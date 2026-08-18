<?php

namespace App\Models;

use App\Models\Concerns\SetsCreatedAt;
use Illuminate\Database\Eloquent\Model;

class ActivityLog extends Model
{
    use SetsCreatedAt;

    public $timestamps = false;

    protected $table = 'activity_logs';

    protected $fillable = [
        'actor_id',
        'action',
        'subject_type',
        'subject_id',
        'meta_json',
        'created_at',
    ];

    protected $casts = [
        'meta_json' => 'array',
        'created_at' => 'datetime',
    ];

    public function actor()
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    /** "agent.approved" → "Agent approved" */
    public function label(): string
    {
        return ucfirst(str_replace(['.', '_'], ' ', $this->action));
    }
}
