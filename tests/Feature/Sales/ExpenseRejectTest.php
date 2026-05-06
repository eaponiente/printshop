<?php

use App\Enums\Expenses\ExpenseStatus;
use App\Models\Branch;
use App\Models\Expense;
use App\Models\User;

// ─── Scenario 1: Different branch rejects an expense assigned to them ───
it('rejects a pending expense assigned to a different branch', function () {
    $branchA = Branch::factory()->create(['name' => 'Branch A']);
    $branchB = Branch::factory()->create(['name' => 'Branch B']);

    $creator = User::factory()->create(['branch_id' => $branchA->id, 'role' => 'admin']);
    $rejector = User::factory()->create(['branch_id' => $branchB->id, 'role' => 'admin']);

    $expense = Expense::factory()
        ->pending()
        ->cash()
        ->forBranch($branchB)
        ->createdBy($creator)
        ->create([
            'amount' => 500,
        ]);

    $this->actingAs($rejector)
        ->post(route('expenses.reject', $expense))
        ->assertSessionHasNoErrors();

    expect($expense->fresh()->status)->toBe(ExpenseStatus::REJECTED->value);
});

// ─── Scenario 2: Debtor branch rejects a credit expense they didn't create ───
it('rejects a pending credit expense when rejector is the debtor branch', function () {
    $branchA = Branch::factory()->create(['name' => 'Branch A (creditor)']);
    $branchB = Branch::factory()->create(['name' => 'Branch B (debtor)']);

    $creator = User::factory()->create(['branch_id' => $branchA->id, 'role' => 'admin']);
    $rejector = User::factory()->create(['branch_id' => $branchB->id, 'role' => 'admin']);

    $expense = Expense::factory()
        ->pending()
        ->credit()
        ->forBranch($branchA)
        ->createdBy($creator)
        ->crossBranchCredit($branchB)
        ->create([
            'amount' => 1500,
        ]);

    $this->actingAs($rejector)
        ->post(route('expenses.reject', $expense))
        ->assertSessionHasNoErrors();

    expect($expense->fresh()->status)->toBe(ExpenseStatus::REJECTED->value);
});

// ─── Scenario 3: Creditor (assigned) branch rejects a credit expense when they didn't create it ───
it('rejects a pending credit expense when rejector is the assigned branch but not the creator', function () {
    $branchA = Branch::factory()->create(['name' => 'Branch A (creditor)']);
    $branchB = Branch::factory()->create(['name' => 'Branch B (debtor/creator)']);

    $creator = User::factory()->create(['branch_id' => $branchB->id, 'role' => 'admin']);
    $rejector = User::factory()->create(['branch_id' => $branchA->id, 'role' => 'admin']);

    $expense = Expense::factory()
        ->pending()
        ->credit()
        ->forBranch($branchA)
        ->createdBy($creator)
        ->crossBranchCredit($branchB)
        ->create([
            'amount' => 2000,
        ]);

    $this->actingAs($rejector)
        ->post(route('expenses.reject', $expense))
        ->assertSessionHasNoErrors();

    expect($expense->fresh()->status)->toBe(ExpenseStatus::REJECTED->value);
});

// ─── Scenario 4: Cannot reject a non-pending expense ───
it('rejects rejection of an already approved expense', function () {
    $branchA = Branch::factory()->create();
    $branchB = Branch::factory()->create();

    $creator = User::factory()->create(['branch_id' => $branchA->id, 'role' => 'admin']);
    $rejector = User::factory()->create(['branch_id' => $branchB->id, 'role' => 'admin']);

    $expense = Expense::factory()
        ->paid()
        ->forBranch($branchB)
        ->createdBy($creator)
        ->create();

    $response = $this->actingAs($rejector)
        ->post(route('expenses.reject', $expense));

    $response->assertSessionHasErrors(['message']);
    expect($expense->fresh()->status)->toBe(ExpenseStatus::PAID->value);
});

// ─── Scenario 5: Superadmin can always reject ───
it('allows superadmin to reject any pending expense', function () {
    $branchA = Branch::factory()->create(['name' => 'Branch A']);
    $branchB = Branch::factory()->create(['name' => 'Branch B']);

    $creator = User::factory()->create(['branch_id' => $branchA->id, 'role' => 'admin']);
    $superadmin = User::factory()->create(['branch_id' => $branchA->id, 'role' => 'superadmin']);

    $expense = Expense::factory()
        ->pending()
        ->cash()
        ->forBranch($branchB)
        ->createdBy($creator)
        ->create([
            'amount' => 300,
        ]);

    $this->actingAs($superadmin)
        ->post(route('expenses.reject', $expense))
        ->assertSessionHasNoErrors();

    expect($expense->fresh()->status)->toBe(ExpenseStatus::REJECTED->value);
});

// ─── Scenario 6: Cannot reject an already rejected or voided expense ───
it('rejects rejection of an already rejected expense', function () {
    $branchA = Branch::factory()->create();
    $branchB = Branch::factory()->create();

    $creator = User::factory()->create(['branch_id' => $branchA->id, 'role' => 'admin']);
    $rejector = User::factory()->create(['branch_id' => $branchB->id, 'role' => 'admin']);

    $expense = Expense::factory()
        ->rejected()
        ->forBranch($branchB)
        ->createdBy($creator)
        ->create();

    $response = $this->actingAs($rejector)
        ->post(route('expenses.reject', $expense));

    $response->assertSessionHasErrors(['message']);
    expect($expense->fresh()->status)->toBe(ExpenseStatus::REJECTED->value);
});

// ─── Scenario 7: User from unrelated branch cannot reject ───
it('rejects rejection from a branch that is not the assigned or debtor branch', function () {
    $branchA = Branch::factory()->create(['name' => 'Branch A (creator)']);
    $branchB = Branch::factory()->create(['name' => 'Branch B (assigned)']);
    $branchC = Branch::factory()->create(['name' => 'Branch C (unrelated)']);

    $creator = User::factory()->create(['branch_id' => $branchA->id, 'role' => 'admin']);
    $unrelatedUser = User::factory()->create(['branch_id' => $branchC->id, 'role' => 'admin']);

    $expense = Expense::factory()
        ->pending()
        ->cash()
        ->forBranch($branchB)
        ->createdBy($creator)
        ->create([
            'amount' => 500,
        ]);

    $this->actingAs($unrelatedUser)
        ->post(route('expenses.reject', $expense))
        ->assertForbidden();

    expect($expense->fresh()->status)->toBe(ExpenseStatus::PENDING->value);
});
