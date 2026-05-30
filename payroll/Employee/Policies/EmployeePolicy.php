<?php

namespace Payroll\Employee\Policies;

use App\Models\Branch;
use App\Models\Payroll\Employee;
use App\Models\User;

class EmployeePolicy
{
    /**
     * Get the special group branch names from config.
     *
     * @return array<int, string>
     */
    protected function getSpecialGroupNames(): array
    {
        return config('company.special_group_branch_names', []);
    }

    /**
     * Check if the user can access an employee's branch.
     * Handles superadmin bypass, same-branch access, and special group sharing.
     */
    protected function canAccessBranch(User $user, Employee $employee): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        $userBranchId = (int) $user->branch_id;
        $employeeBranchId = (int) $employee->branch_id;

        if ($userBranchId === $employeeBranchId) {
            return true;
        }

        $specialGroupNames = $this->getSpecialGroupNames();
        if (empty($specialGroupNames)) {
            return false;
        }

        $userBranchName = $user->branch?->name;
        $employeeBranchName = $employee->branch?->name;

        if ($userBranchName && $employeeBranchName) {
            return in_array($userBranchName, $specialGroupNames, true)
                && in_array($employeeBranchName, $specialGroupNames, true);
        }

        return false;
    }

    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Employee $employee): bool
    {
        return $this->canAccessBranch($user, $employee);
    }

    public function create(User $user): bool
    {
        return $user->isSuperAdmin() || $user->isAdmin();
    }

    public function update(User $user, Employee $employee): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        return $user->isAdmin() && $this->canAccessBranch($user, $employee);
    }

    public function delete(User $user, Employee $employee): bool
    {
        return $user->isSuperAdmin();
    }

    public function deactivate(User $user, Employee $employee): bool
    {
        if ($user->id === $employee->user?->id) {
            return false;
        }

        if ($user->isSuperAdmin()) {
            return true;
        }

        return $user->isAdmin() && $this->canAccessBranch($user, $employee);
    }

    public function rehire(User $user, Employee $employee): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        return $user->isAdmin() && $this->canAccessBranch($user, $employee);
    }

    public function editOwn(User $user, Employee $employee): bool
    {
        if ($employee->status->value !== 'active') {
            return false;
        }

        return $user->employee_id === $employee->id;
    }
}
