<?php

namespace App\Enums;

enum SubscriptionStatus: string
{
    case ACTIVE = 'active';
    case EXPIRED = 'expired';
    case CANCELLED = 'cancelled';
    case PENDING_RENEWAL = 'pending_renewal';
    case PAST_DUE = 'past_due';
}
