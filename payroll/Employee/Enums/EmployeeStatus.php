<?php

namespace Payroll\Employee\Enums;

enum EmployeeStatus: string
{
    case ACTIVE = 'active';
    case RESIGNED = 'resigned';
    case TERMINATED = 'terminated';
}
