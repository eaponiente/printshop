<?php

namespace Payroll\Attendance\Enums;

enum PunchSource: string
{
    case SELF_SERVICE = 'self_service';
    case MANUAL = 'manual';
    case CORRECTION = 'correction';

    public function label(): string
    {
        return match ($this) {
            self::SELF_SERVICE => 'Self-Service',
            self::MANUAL => 'Manual',
            self::CORRECTION => 'Correction',
        };
    }
}
