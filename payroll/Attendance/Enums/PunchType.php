<?php

namespace Payroll\Attendance\Enums;

enum PunchType: string
{
    case IN = 'in';
    case LUNCH_OUT = 'lunch_out';
    case LUNCH_IN = 'lunch_in';
    case OUT = 'out';
    case OVERTIME_IN = 'overtime_in';
    case OVERTIME_OUT = 'overtime_out';

    public function label(): string
    {
        return match ($this) {
            self::IN => 'Punch In',
            self::LUNCH_OUT => 'Lunch Out',
            self::LUNCH_IN => 'Lunch In',
            self::OUT => 'Punch Out',
            self::OVERTIME_IN => 'Overtime In',
            self::OVERTIME_OUT => 'Overtime Out',
        };
    }
}
