<?php

namespace App\Support;

use App\Models\ActivityLog;
use App\Models\User;

/** Audit trail for privileged actions (BACKEND_GUIDE §10). */
class ActivityLogger
{
    public static function log(
        string $action,
        ?User $actor = null,
        ?string $subjectType = null,
        ?int $subjectId = null,
        array $meta = [],
    ): ActivityLog {
        return ActivityLog::create([
            'actor_id' => $actor?->id ?? auth()->id(),
            'action' => $action,
            'subject_type' => $subjectType,
            'subject_id' => $subjectId,
            'meta_json' => $meta ?: null,
            'created_at' => now(),
        ]);
    }

    public static function lead(string $action, int $leadId, array $meta = [], ?User $actor = null): ActivityLog
    {
        return self::log($action, $actor, 'lead', $leadId, $meta);
    }

    public static function user(string $action, int $userId, array $meta = [], ?User $actor = null): ActivityLog
    {
        return self::log($action, $actor, 'user', $userId, $meta);
    }
}
