<?php

namespace App\Enums;

enum SellerSubtype: string
{
    case STANDARD = 'standard';
    case EXPIRED = 'expired';
    case FSBO = 'fsbo';
    case FRBO = 'frbo';
    case PRE_FORECLOSURE = 'pre_foreclosure';
    case PROBATE = 'probate';
    case RELOCATION = 'relocation';

    public function label(): string
    {
        return match($this) {
            self::STANDARD => 'Standard Listing',
            self::EXPIRED => 'Expired Listing',
            self::FSBO => 'For Sale By Owner (FSBO)',
            self::FRBO => 'For Rent By Owner (FRBO)',
            self::PRE_FORECLOSURE => 'Pre-Foreclosure',
            self::PROBATE => 'Probate / Estate Sale',
            self::RELOCATION => 'Relocation',
        };
    }
}
