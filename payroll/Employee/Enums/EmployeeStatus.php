<?php

namespace Payroll\Employee\Enums;

enum EmployeeStatus: string
{
    case ACTIVE = 'active';
    case INACTIVE = 'inactive';
    case RESIGNED = 'resigned';
    case TERMINATED = 'terminated';

    public function label(): string
    {
        return match ($this) {
            self::ACTIVE => 'Active',
            self::INACTIVE => 'Inactive',
            self::RESIGNED => 'Resigned',
            self::TERMINATED => 'Terminated',
        };
    }

    public function allowsLogin(): bool
    {
        return $this === self::ACTIVE;
    }
}
