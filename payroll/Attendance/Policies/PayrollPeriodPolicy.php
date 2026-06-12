<?php

namespace Payroll\Attendance\Policies;

use App\Models\User;

class PayrollPeriodPolicy
{
    protected function canAccessBranch(User $user, int $branchId): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        return (int) $user->branch_id === $branchId;
    }

    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, int $branchId): bool
    {
        if ($user->isStaff()) {
            return false;
        }

        return $this->canAccessBranch($user, $branchId);
    }

    public function generate(User $user, int $branchId): bool
    {
        if ($user->isStaff()) {
            return false;
        }

        return $this->canAccessBranch($user, $branchId);
    }

    public function approve(User $user, int $branchId): bool
    {
        return $user->isSuperAdmin();
    }

    public function void(User $user): bool
    {
        return $user->isSuperAdmin();
    }

    public function delete(User $user, int $branchId): bool
    {
        if ($user->isStaff()) {
            return false;
        }

        return $this->canAccessBranch($user, $branchId);
    }
}
