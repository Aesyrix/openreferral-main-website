<?php

namespace App\Enums;

enum LeadStatus: string
{
    case NEW = 'new';
    case IN_PROGRESS = 'in_progress';
    case CONVERTED = 'converted';
    case REJECTED = 'rejected';

    public function label(): string
    {
        return match($this) {
            self::NEW => 'New',
            self::IN_PROGRESS => 'In Progress',
            self::CONVERTED => 'Converted',
            self::REJECTED => 'Rejected',
        };
    }

    public function badgeClass(): string
    {
        return match($this) {
            self::NEW => 'badge-blue',
            self::IN_PROGRESS => 'badge-amber',
            self::CONVERTED => 'badge-green',
            self::REJECTED => 'badge-red',
        };
    }
}
