<?php

namespace Payroll\SewedItem\Policies;

use App\Models\Payroll\SewedItem;
use App\Models\User;

class SewedItemPolicy
{
    private function staffCanEditInBranch(User $user): bool
    {
        $employee = $user->employee;

        return $employee !== null && $employee->can_edit_sewed_items;
    }

    public function viewAny(User $user): bool
    {
        return $user->isSuperAdmin() || $user->isAdmin() || $user->isStaff();
    }

    public function view(User $user, SewedItem $sewedItem): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        if ($user->isAdmin() && (int) $sewedItem->branch_id === (int) $user->branch_id) {
            return true;
        }

        if ($user->isStaff()) {
            if ((int) $sewedItem->user_id === $user->id) {
                return true;
            }

            if ($this->staffCanEditInBranch($user) && (int) $sewedItem->branch_id === (int) $user->branch_id) {
                return true;
            }
        }

        return false;
    }

    public function create(User $user): bool
    {
        return $user->isSuperAdmin() || $user->isAdmin() || $user->isStaff();
    }

    public function update(User $user, SewedItem $sewedItem): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        if ($user->isAdmin() && (int) $sewedItem->branch_id === (int) $user->branch_id) {
            return true;
        }

        if ($user->isStaff()) {
            if ((int) $sewedItem->user_id === $user->id) {
                return true;
            }

            if ($this->staffCanEditInBranch($user) && (int) $sewedItem->branch_id === (int) $user->branch_id) {
                return true;
            }
        }

        return false;
    }

    public function delete(User $user, SewedItem $sewedItem): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        if ($user->isAdmin() && (int) $sewedItem->branch_id === (int) $user->branch_id) {
            return true;
        }

        return false;
    }
}
