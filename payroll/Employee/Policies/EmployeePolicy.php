<?php

namespace Payroll\Employee\Policies;

use App\Models\Payroll\Employee;
use App\Models\User;

class EmployeePolicy
{
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

    protected function canAccessBranch(User $user, Employee $employee): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        return $user->branch_id === $employee->branch_id;
    }
}
