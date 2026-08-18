<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RejectionReason extends Model
{
    use HasFactory;

    protected $fillable = [
        'slug',
        'label',
        'requires_note',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'requires_note' => 'boolean',
        'is_active' => 'boolean',
    ];
}
