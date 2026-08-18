<?php

namespace App\Enums;

enum LeadType: string
{
    case BUYER = 'buyer';
    case SELLER = 'seller';
    case RENTER = 'renter';
    case LANDLORD = 'landlord';
    case INVESTOR = 'investor';

    public function label(): string
    {
        return ucfirst($this->value);
    }
}
