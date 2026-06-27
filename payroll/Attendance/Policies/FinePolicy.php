<?php

namespace Payroll\Attendance\Policies;

use App\Models\User;

class FinePolicy
{
    protected function canAccessEmployee(User $user, int $employeeBranchId): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        return (int) $user->branch_id === $employeeBranchId;
    }

    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user): bool
    {
        return true;
    }

    public function mark(User $user, int $employeeBranchId): bool
    {
        if ($user->isStaff()) {
            return false;
        }

        return $this->canAccessEmployee($user, $employeeBranchId);
    }

    public function manageTypes(User $user): bool
    {
        return $user->isSuperAdmin();
    }
}
