<?php

namespace Payroll\Attendance\Policies;

use App\Models\User;

class HolidayPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return $user->isSuperAdmin() || $user->isAdmin();
    }

    public function update(User $user): bool
    {
        return $user->isSuperAdmin() || $user->isAdmin();
    }

    public function delete(User $user): bool
    {
        return $user->isSuperAdmin() || $user->isAdmin();
    }
}
