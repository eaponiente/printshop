<?php

namespace Payroll\Employee\Enums;

enum EmployeePosition: string
{
    case REGULAR = 'regular';
    case CONTRACTUAL = 'contractual';
    case PROJECT_BASED = 'project_based';

    public function label(): string
    {
        return match ($this) {
            self::REGULAR => 'Regular',
            self::CONTRACTUAL => 'Contractual',
            self::PROJECT_BASED => 'Project-Based',
        };
    }
}
