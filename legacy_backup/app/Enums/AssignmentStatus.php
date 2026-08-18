<?php

namespace App\Enums;

enum AssignmentStatus: string
{
    case PENDING = 'pending';
    case ACCEPTED = 'accepted';
    case REJECTED = 'rejected';
    case EXPIRED = 'expired';
    case REASSIGNED = 'reassigned';
}
