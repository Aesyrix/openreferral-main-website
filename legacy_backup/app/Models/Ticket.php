<?php

namespace App\Models;

use App\Enums\TicketStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Ticket extends Model
{
    use HasFactory;

    protected $fillable = [
        'uuid',
        'subject',
        'body',
        'status',
        'priority',
        'requester_id',
        'assignee_id',
        'lead_id',
    ];

    protected $casts = [
        'status' => TicketStatus::class,
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

    public function requester()
    {
        return $this->belongsTo(User::class, 'requester_id');
    }

    public function assignee()
    {
        return $this->belongsTo(User::class, 'assignee_id');
    }

    public function lead()
    {
        return $this->belongsTo(Lead::class);
    }

    public function documents()
    {
        return $this->morphMany(Document::class, 'documentable');
    }
}
