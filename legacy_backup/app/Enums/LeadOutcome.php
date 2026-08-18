<?php

namespace App\Enums;

enum LeadOutcome: string
{
    case CONNECTED = 'connected';
    case VOICEMAIL = 'voicemail';
    case NO_ANSWER = 'no_answer';
    case WRONG_NUMBER = 'wrong_number';
    case NOT_INTERESTED = 'not_interested';
    case NURTURE = 'nurture';
    case APPOINTMENT_BOOKED = 'appointment_booked';
    case WENT_UNDER_CONTRACT = 'went_under_contract';
    case CLOSED = 'closed';
    case LOST = 'lost';

    public function label(): string
    {
        return match($this) {
            self::CONNECTED => 'Connected / Spoke',
            self::VOICEMAIL => 'Left Voicemail',
            self::NO_ANSWER => 'No Answer',
            self::WRONG_NUMBER => 'Wrong Phone Number',
            self::NOT_INTERESTED => 'Not Interested',
            self::NURTURE => 'Nurture for Future',
            self::APPOINTMENT_BOOKED => 'Appointment Booked',
            self::WENT_UNDER_CONTRACT => 'Went Under Contract',
            self::CLOSED => 'Deal Closed Successfully',
            self::LOST => 'Deal Lost / Cold',
        };
    }
}
