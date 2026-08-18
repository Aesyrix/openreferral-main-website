<?php

namespace App\Enums;

enum RejectionReason: string
{
    case NOT_IN_SERVICE_AREA = 'not_in_service_area';
    case NOT_INTERESTED = 'not_interested';
    case NOT_SERIOUS = 'not_serious';
    case BUDGET_TOO_LOW = 'budget_too_low';
    case PROPERTY_TYPE_MISMATCH = 'property_type_mismatch';
    case ALREADY_WORKING_OTHER = 'already_working_other';
    case OTHER = 'other';

    public function label(): string
    {
        return match ($this) {
            self::NOT_IN_SERVICE_AREA => 'Not in my service area',
            self::NOT_INTERESTED => 'Client not interested',
            self::NOT_SERIOUS => 'Client not serious',
            self::BUDGET_TOO_LOW => 'Budget too low',
            self::PROPERTY_TYPE_MISMATCH => 'Property type mismatch',
            self::ALREADY_WORKING_OTHER => 'Already working with another agent',
            self::OTHER => 'Other',
        };
    }

    /** @return array<string,string> value => label */
    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $case) => [$case->value => $case->label()])
            ->all();
    }
}
