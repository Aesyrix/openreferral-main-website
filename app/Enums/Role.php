<?php

namespace App\Enums;

enum Role: string
{
    case AGENT = 'agent';
    case STAFF = 'staff';
    case ADMIN = 'admin';

    public function label(): string
    {
        return match ($this) {
            self::AGENT => 'Agent',
            self::STAFF => 'Staff',
            self::ADMIN => 'Admin',
        };
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::AGENT => 'badge-blue',
            self::STAFF => 'badge-teal',
            self::ADMIN => 'badge-indigo',
        };
    }
}
