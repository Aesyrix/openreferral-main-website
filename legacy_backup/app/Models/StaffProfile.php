<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StaffProfile extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'team',
        'title',
        'can_reassign',
        'can_export',
    ];

    protected $casts = [
        'can_reassign' => 'boolean',
        'can_export' => 'boolean',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
