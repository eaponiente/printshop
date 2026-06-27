<?php

namespace Payroll\Attendance\Policies;

use App\Models\User;

class AttendanceSheetPolicy
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

    public function view(User $user, int $employeeBranchId): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        if ($user->isStaff()) {
            return false;
        }

        return (int) $user->branch_id === $employeeBranchId;
    }

    public function viewOwn(User $user, int $employeeId, int $ownEmployeeId): bool
    {
        return $user->isSuperAdmin() || $employeeId === $ownEmployeeId;
    }
}
