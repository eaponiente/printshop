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
     * Determine whether the user can approve the expense.
     */
    public function approve(User $user, Expense $expense): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        if (! $user->isAdmin()) {
            return false;
        }

        $currentBranchId = (int) $user->branch_id;
        $creatorBranchId = (int) $expense->user->branch_id;

        // The creator's branch cannot approve their own expense
        if ($currentBranchId === $creatorBranchId) {
            return false;
        }

        // Must be the assigned branch, creditor branch, or debtor branch
        return $currentBranchId === (int) $expense->branch_id
            || ($expense->creditor_branch_id && $currentBranchId === (int) $expense->creditor_branch_id)
            || ($expense->debtor_branch_id && $currentBranchId === (int) $expense->debtor_branch_id);
    }

    /**
     * Determine whether the user can reject the expense.
     */
    public function reject(User $user, Expense $expense): bool
    {
        return $this->approve($user, $expense);
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
