<?php

namespace App\Enums;

enum LeadIntent: string
{
    case BUYING = 'buying';
    case SELLING = 'selling';

    public function label(): string
    {
        return match ($this) {
            self::BUYING => 'Buying',
            self::SELLING => 'Selling',
        };
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::BUYING => 'badge-sky',
            self::SELLING => 'badge-violet',
        };
    }
}
