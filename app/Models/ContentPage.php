<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ContentPage extends Model
{
    public const SLUGS = ['about', 'privacy', 'terms', 'help'];

    public $timestamps = false;

    protected $table = 'content_pages';

    protected $fillable = [
        'slug',
        'title',
        'intro',
        'body_json',
        'updated_at',
    ];

    protected $casts = [
        'body_json' => 'array',
        'updated_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::saving(function (self $page) {
            $page->updated_at = now();
        });
    }

    /** Normalised sections list, always [{heading, body}, ...]. */
    public function sections(): array
    {
        return collect($this->body_json ?? [])
            ->map(fn ($section) => [
                'heading' => (string) ($section['heading'] ?? ''),
                'body' => (string) ($section['body'] ?? ''),
            ])
            ->values()
            ->all();
    }
}
