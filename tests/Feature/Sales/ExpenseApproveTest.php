<?php

use App\Enums\Expenses\ExpenseStatus;
use App\Models\Branch;
use App\Models\CashOnHand;
use App\Models\Expense;
use App\Models\Transaction;
use App\Models\User;

// ─── Scenario 1: Different branch approves an expense assigned to them ───
it('approves a pending expense assigned to a different branch', function () {
    $branchA = Branch::factory()->create(['name' => 'Branch A']);
    $branchB = Branch::factory()->create(['name' => 'Branch B']);

    $creator = User::factory()->create(['branch_id' => $branchA->id, 'role' => 'admin']);
    $approver = User::factory()->create(['branch_id' => $branchB->id, 'role' => 'admin']);

    $expense = Expense::factory()
        ->pending()
        ->cash()
        ->forBranch($branchB)
        ->createdBy($creator)
        ->create([
            'amount' => 500,
        ]);

    $this->actingAs($approver)
        ->post(route('expenses.approve', $expense))
        ->assertSessionHasNoErrors();

    expect($expense->fresh()->status)->toBe(ExpenseStatus::PAID->value);
});

// ─── Scenario 2: Debtor branch approves a credit expense they didn't create ───
it('approves a pending credit expense when approver is the debtor branch', function () {
    $branchA = Branch::factory()->create(['name' => 'Branch A (creditor)']);
    $branchB = Branch::factory()->create(['name' => 'Branch B (debtor)']);

    $creator = User::factory()->create(['branch_id' => $branchA->id, 'role' => 'admin']);
    $approver = User::factory()->create(['branch_id' => $branchB->id, 'role' => 'admin']);

    $expense = Expense::factory()
        ->pending()
        ->credit()
        ->forBranch($branchA)
        ->createdBy($creator)
        ->crossBranchCredit($branchB)
        ->create([
            'amount' => 1000,
        ]);

    $this->actingAs($approver)
        ->post(route('expenses.approve', $expense))
        ->assertSessionHasNoErrors();

    expect($expense->fresh()->status)->toBe(ExpenseStatus::PAID->value);
});

// ─── Scenario 3: Creditor (assigned) branch approves a credit expense when they didn't create it ───
it('approves a pending credit expense when approver is the assigned branch but not the creator', function () {
    $branchA = Branch::factory()->create(['name' => 'Branch A (creditor)']);
    $branchB = Branch::factory()->create(['name' => 'Branch B (debtor)']);

    $creator = User::factory()->create(['branch_id' => $branchB->id, 'role' => 'admin']);
    $approver = User::factory()->create(['branch_id' => $branchA->id, 'role' => 'admin']);

    $expense = Expense::factory()
        ->pending()
        ->credit()
        ->forBranch($branchA)
        ->createdBy($creator)
        ->crossBranchCredit($branchB)
        ->create([
            'amount' => 1000,
        ]);

    $this->actingAs($approver)
        ->post(route('expenses.approve', $expense))
        ->assertSessionHasNoErrors();

    expect($expense->fresh()->status)->toBe(ExpenseStatus::PAID->value);
});

// ─── Scenario 4: Reject approval of a non-pending expense ───
it('rejects approval of an already paid expense', function () {
    $branchA = Branch::factory()->create();
    $branchB = Branch::factory()->create();

    $creator = User::factory()->create(['branch_id' => $branchA->id, 'role' => 'admin']);
    $approver = User::factory()->create(['branch_id' => $branchB->id, 'role' => 'admin']);

    $expense = Expense::factory()
        ->paid()
        ->forBranch($branchB)
        ->createdBy($creator)
        ->create();

    $response = $this->actingAs($approver)
        ->post(route('expenses.approve', $expense));

    $response->assertSessionHasErrors(['message']);
    expect($expense->fresh()->status)->toBe(ExpenseStatus::PAID->value);
});

// ─── Scenario 5: Cash expense — deducts from CashOnHand ───
it('deducts cash on hand when approving a cash payment expense', function () {
    $branchA = Branch::factory()->create();
    $branchB = Branch::factory()->create();

    // Seed a starting cash balance for the target branch
    CashOnHand::create([
        'branch_id' => $branchB->id,
        'amount' => 10000,
    ]);

    $creator = User::factory()->create(['branch_id' => $branchA->id, 'role' => 'admin']);
    $approver = User::factory()->create(['branch_id' => $branchB->id, 'role' => 'admin']);

    $expense = Expense::factory()
        ->pending()
        ->cash()
        ->forBranch($branchB)
        ->createdBy($creator)
        ->create([
            'amount' => 750,
        ]);

    $this->actingAs($approver)
        ->post(route('expenses.approve', $expense))
        ->assertSessionHasNoErrors();

    $cash = CashOnHand::where('branch_id', $branchB->id)->first();
    expect((float) $cash->amount)->toBe(9250.0);
});

// ─── Scenario 6: Credit cross-branch — creates a debit Transaction on debtor's branch ───
it('creates a debit transaction when approving a credit cross-branch expense', function () {
    $branchA = Branch::factory()->create(['name' => 'Creditor Branch']);
    $branchB = Branch::factory()->create(['name' => 'Debtor Branch']);

    $creator = User::factory()->create(['branch_id' => $branchA->id, 'role' => 'admin']);
    $approver = User::factory()->create(['branch_id' => $branchB->id, 'role' => 'admin']);

    $expense = Expense::factory()
        ->pending()
        ->credit()
        ->forBranch($branchA)
        ->createdBy($creator)
        ->crossBranchCredit($branchB)
        ->create([
            'amount' => 2500,
        ]);

    expect($expense->payment_type)->toBe('credit')
        ->and($expense->debtor_branch_id)->not->toBeNull();

    $this->actingAs($approver)
        ->post(route('expenses.approve', $expense))
        ->assertSessionHasNoErrors();

    $transaction = Transaction::where('branch_id', $branchB->id)
        ->first();

    expect($transaction)->not->toBeNull()
        ->and((float) $transaction->amount_total)->toBe(2500.0)
        ->and($transaction->payments()->count())->toBe(1)
        ->and($transaction->payments()->first()->payment_type)->toBe('debit');
});

// ─── Scenario 7: Superadmin can always approve ───
it('allows superadmin to approve any pending expense', function () {
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
        ->post(route('expenses.approve', $expense))
        ->assertSessionHasNoErrors();

    expect($expense->fresh()->status)->toBe(ExpenseStatus::PAID->value);
});

// ─── Scenario 8: User from another unrelated branch cannot approve ───
it('rejects approval from a branch that is not the assigned or debtor branch', function () {
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
        ->post(route('expenses.approve', $expense))
        ->assertForbidden();

    expect($expense->fresh()->status)->toBe(ExpenseStatus::PENDING->value);
});
