<?php

namespace App\Policies;

use App\Models\Expense;
use App\Models\User;

class ExpensePolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return false;
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, PurchaseOrder $purchaseOrder): bool
    {
        return false;
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return false;
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Expense $expense): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        // 2. Staff can only update if it belongs to their branch
        return $user->isAdmin() && (int) $user->branch_id === (int) $expense->branch_id;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, PurchaseOrder $purchaseOrder): bool
    {
        return false;
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function void(User $user, Expense $expense): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        // 2. Staff can only update if it belongs to their branch
        return $user->isAdmin() && (int) $user->branch_id === (int) $expense->branch_id;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, PurchaseOrder $purchaseOrder): bool
    {
        return false;
    }
}
