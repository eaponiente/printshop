<?php

namespace Payroll\Attendance\Policies;

use App\Models\User;

class TimeLogPolicy
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

    public function punch(User $user, int $employeeBranchId): bool
    {
        return $this->canAccessEmployee($user, $employeeBranchId);
    }

    public function manualLog(User $user, int $employeeBranchId): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        if ($user->isStaff()) {
            return false;
        }

        return (int) $user->branch_id === $employeeBranchId;
    }

    public function useCustomTimestamp(User $user): bool
    {
        return config('app.enable_custom_punch_time') || $user->isSuperAdmin();
    }
}
