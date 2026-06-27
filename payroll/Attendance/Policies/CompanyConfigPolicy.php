<?php

namespace Payroll\Attendance\Policies;

use App\Models\User;

class CompanyConfigPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user): bool
    {
        return true;
    }

    public function edit(User $user): bool
    {
        return $user->isSuperAdmin();
    }
}
