<?php

namespace Payroll\Attendance\Enums;

enum PayrollPeriodStatus: string
{
    case DRAFT = 'draft';
    case APPROVED = 'approved';
    case PAID = 'paid';
    case VOIDED = 'voided';

    public function label(): string
    {
        return match ($this) {
            self::DRAFT => 'Draft',
            self::APPROVED => 'Approved',
            self::PAID => 'Paid',
            self::VOIDED => 'Voided',
        };
    }
}
