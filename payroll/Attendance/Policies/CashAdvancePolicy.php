<?php

namespace Payroll\Attendance\Policies;

use App\Models\User;

class CashAdvancePolicy
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
        return ! $user->isStaff();
    }

    public function view(User $user): bool
    {
        return ! $user->isStaff();
    }

    public function request(User $user, int $employeeBranchId): bool
    {
        if ($user->isStaff()) {
            return false;
        }

        return $this->canAccessEmployee($user, $employeeBranchId);
    }

    public function approve(User $user, int $requestorBranchId, ?int $requestorUserId): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        if ($user->isStaff()) {
            return false;
        }

        if ($user->id === $requestorUserId) {
            return false;
        }

        if ($user->isAdmin()) {
            if ((int) $user->branch_id !== $requestorBranchId) {
                return false;
            }

            if ($requestorUserId) {
                $requestor = User::find($requestorUserId);
                if ($requestor && ! $requestor->isStaff()) {
                    return false;
                }
            }

            return true;
        }

        return false;
    }

    public function deny(User $user, int $requestorBranchId, ?int $requestorUserId): bool
    {
        return $this->approve($user, $requestorBranchId, $requestorUserId);
    }
}
