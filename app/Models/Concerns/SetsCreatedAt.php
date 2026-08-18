<?php

namespace App\Models\Concerns;

/**
 * These append-only tables carry a bare `created_at` (no `updated_at`), so
 * Eloquent's timestamp handling is off. Fill it on insert anyway so freshly
 * created models can be rendered without a round-trip to the database.
 */
trait SetsCreatedAt
{
    public static function bootSetsCreatedAt(): void
    {
        static::creating(function ($model) {
            $model->created_at ??= now();
        });
    }
}
