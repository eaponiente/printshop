<?php

namespace Payroll\Employee\Enums;

enum EmployeePosition: string
{
    case REGULAR = 'regular';
    case PROBATION = 'probation';

    public function label(): string
    {
        return match ($this) {
            self::REGULAR => 'Regular',
            self::PROBATION => 'Probation',
        };
    }
}
