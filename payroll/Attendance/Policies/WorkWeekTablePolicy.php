<?php

namespace Payroll\Attendance\Policies;

use App\Models\User;

class WorkWeekTablePolicy
{
    protected function canAccessBranch(User $user, int $branchId): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        return (int) $user->branch_id === $branchId;
    }

    public function view(User $user, int $branchId): bool
    {
        if ($user->isStaff()) {
            return false;
        }

        return $this->canAccessBranch($user, $branchId);
    }
}
