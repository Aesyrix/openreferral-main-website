<?php

namespace App\Models;

use App\Models\Concerns\SetsCreatedAt;
use Illuminate\Database\Eloquent\Model;

class Device extends Model
{
    use SetsCreatedAt;

    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'token',
        'platform',
        'last_seen_at',
        'created_at',
    ];

    protected $casts = [
        'last_seen_at' => 'datetime',
        'created_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
