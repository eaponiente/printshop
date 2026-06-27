<?php

namespace App\Policies;

use App\Models\Payroll\PayrollSetting;
use App\Models\User;

class PayrollSettingPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->role === 'superadmin';
    }

    public function view(User $user, ?PayrollSetting $payrollSetting = null): bool
    {
        return $user->role === 'superadmin';
    }

    public function update(User $user, ?PayrollSetting $payrollSetting = null): bool
    {
        return $user->role === 'superadmin';
    }
}
