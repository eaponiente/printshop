<?php

namespace Payroll\Attendance\Policies;

use App\Models\User;

class LeaveRequestPolicy
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

    public function submit(User $user, int $employeeBranchId): bool
    {
        return $this->canAccessEmployee($user, $employeeBranchId);
    }

    public function approve(User $user, int $requestorBranchId, int $requestorUserId): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        if ($user->isStaff()) {
            return false;
        }

        // Superior-only: cannot approve own request
        if ($user->id === $requestorUserId) {
            return false;
        }

        return (int) $user->branch_id === $requestorBranchId;
    }

    public function deny(User $user, int $requestorBranchId, int $requestorUserId): bool
    {
        return $this->approve($user, $requestorBranchId, $requestorUserId);
    }
}
