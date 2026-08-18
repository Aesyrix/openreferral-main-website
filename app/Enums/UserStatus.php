<?php

namespace App\Enums;

enum UserStatus: string
{
    case PENDING = 'pending';
    case ACTIVE = 'active';
    case SUSPENDED = 'suspended';
    case REJECTED = 'rejected';

    public function label(): string
    {
        return match ($this) {
            self::PENDING => 'Pending Approval',
            self::ACTIVE => 'Active',
            self::SUSPENDED => 'Suspended',
            self::REJECTED => 'Rejected',
        };
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::PENDING => 'badge-amber',
            self::ACTIVE => 'badge-green',
            self::SUSPENDED => 'badge-orange',
            self::REJECTED => 'badge-red',
        };
    }
}
