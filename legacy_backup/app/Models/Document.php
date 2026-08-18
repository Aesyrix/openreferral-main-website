<?php

namespace App\Models;

use App\Enums\DocumentCategory;
use App\Enums\DocumentVisibility;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Document extends Model
{
    use HasFactory;

    protected $fillable = [
        'uuid',
        'documentable_type',
        'documentable_id',
        'uploaded_by_id',
        'disk',
        'path',
        'original_filename',
        'mime_type',
        'size_bytes',
        'category',
        'visibility',
        'virus_scan_status',
    ];

    protected $casts = [
        'category' => DocumentCategory::class,
        'visibility' => DocumentVisibility::class,
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

    public function documentable()
    {
        return $this->morphTo();
    }

    public function uploader()
    {
        return $this->belongsTo(User::class, 'uploaded_by_id');
    }
}
