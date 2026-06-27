<?php

namespace Payroll\Attendance\Enums;

enum HolidayType: string
{
    case REGULAR = 'regular';
    case SPECIAL = 'special';

    public function label(): string
    {
        return match ($this) {
            self::REGULAR => 'Regular Holiday',
            self::SPECIAL => 'Special Non-Working Day',
        };
    }
}
