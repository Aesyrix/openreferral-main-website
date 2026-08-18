<?php

namespace App\Enums;

enum TransactionType: string
{
    case LEAD_FEE = 'lead_fee';
    case SUBSCRIPTION = 'subscription';
    case ADJUSTMENT = 'adjustment';

    public function label(): string
    {
        return match ($this) {
            self::LEAD_FEE => 'Lead Fee',
            self::SUBSCRIPTION => 'Subscription',
            self::ADJUSTMENT => 'Adjustment',
        };
    }
}
