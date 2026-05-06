<?php

use App\Models\Branch;
use App\Models\Expense;
use App\Models\User;

// ── Visibility Matrix Tests ──
// These verify that approve/reject buttons are shown/hidden correctly
// by testing which users receive 403 vs 200 from the approve/reject endpoints.

beforeEach(function () {
    $this->branchA = Branch::factory()->create(['name' => 'Branch A (creator home)']);
    $this->branchB = Branch::factory()->create(['name' => 'Branch B (assigned)']);
    $this->branchC = Branch::factory()->create(['name' => 'Branch C (unrelated)']);

    $this->creator = User::factory()->create([
        'branch_id' => $this->branchA->id,
        'role' => 'admin',
    ]);

    $this->assignedAdmin = User::factory()->create([
        'branch_id' => $this->branchB->id,
        'role' => 'admin',
    ]);

    $this->unrelatedAdmin = User::factory()->create([
        'branch_id' => $this->branchC->id,
        'role' => 'admin',
    ]);

    $this->superadmin = User::factory()->create([
        'branch_id' => $this->branchA->id,
        'role' => 'superadmin',
    ]);

    $this->staff = User::factory()->create([
        'branch_id' => $this->branchB->id,
        'role' => 'staff',
    ]);
});

// ─── SCENARIO 1: Expense assigned to different branch (non-credit) ───
describe('Non-credit expense assigned to Branch B, created by Branch A', function () {
    beforeEach(function () {
        $this->expense = Expense::factory()
            ->pending()
            ->cash()
            ->forBranch($this->branchB)
            ->createdBy($this->creator)
            ->create(['amount' => 500]);
    });

    it('hides approve/reject from the creator branch (Branch A)', function () {
        $this->actingAs($this->creator)
            ->post(route('expenses.approve', $this->expense))
            ->assertForbidden();

        $this->actingAs($this->creator)
            ->post(route('expenses.reject', $this->expense))
            ->assertForbidden();
    });

    it('shows approve/reject to the assigned branch (Branch B)', function () {
        $this->actingAs($this->assignedAdmin)
            ->post(route('expenses.approve', $this->expense))
            ->assertSessionHasNoErrors();

        // Recreate because previous approve changed status
        $expense2 = Expense::factory()
            ->pending()
            ->cash()
            ->forBranch($this->branchB)
            ->createdBy($this->creator)
            ->create(['amount' => 500]);

        $this->actingAs($this->assignedAdmin)
            ->post(route('expenses.reject', $expense2))
            ->assertSessionHasNoErrors();
    });

    it('hides approve/reject from an unrelated branch (Branch C)', function () {
        $this->actingAs($this->unrelatedAdmin)
            ->post(route('expenses.approve', $this->expense))
            ->assertForbidden();

        $this->actingAs($this->unrelatedAdmin)
            ->post(route('expenses.reject', $this->expense))
            ->assertForbidden();
    });

    it('shows approve/reject to superadmin', function () {
        $this->actingAs($this->superadmin)
            ->post(route('expenses.approve', $this->expense))
            ->assertSessionHasNoErrors();

        $expense2 = Expense::factory()
            ->pending()
            ->cash()
            ->forBranch($this->branchB)
            ->createdBy($this->creator)
            ->create(['amount' => 500]);

        $this->actingAs($this->superadmin)
            ->post(route('expenses.reject', $expense2))
            ->assertSessionHasNoErrors();
    });

    it('hides approve/reject from staff users', function () {
        $this->actingAs($this->staff)
            ->post(route('expenses.approve', $this->expense))
            ->assertForbidden();

        $this->actingAs($this->staff)
            ->post(route('expenses.reject', $this->expense))
            ->assertForbidden();
    });
});

// ─── SCENARIO 2: Credit expense — Branch A = creditor, Branch B = debtor, created by Branch A ───
describe('Credit expense: creditor=BranchA, debtor=BranchB, created by BranchA', function () {
    beforeEach(function () {
        $this->expense = Expense::factory()
            ->pending()
            ->credit()
            ->forBranch($this->branchA)
            ->createdBy($this->creator)
            ->crossBranchCredit($this->branchA, $this->branchB)
            ->create(['amount' => 1000]);
    });

    it('hides approve/reject from the creator/creditor branch (Branch A)', function () {
        $this->actingAs($this->creator)
            ->post(route('expenses.approve', $this->expense))
            ->assertForbidden();

        $this->actingAs($this->creator)
            ->post(route('expenses.reject', $this->expense))
            ->assertForbidden();
    });

    it('shows approve/reject to the debtor branch (Branch B)', function () {
        $this->actingAs($this->assignedAdmin)
            ->post(route('expenses.approve', $this->expense))
            ->assertSessionHasNoErrors();

        $expense2 = Expense::factory()
            ->pending()
            ->credit()
            ->forBranch($this->branchA)
            ->createdBy($this->creator)
            ->crossBranchCredit($this->branchA, $this->branchB)
            ->create(['amount' => 1000]);

        $this->actingAs($this->assignedAdmin)
            ->post(route('expenses.reject', $expense2))
            ->assertSessionHasNoErrors();
    });

    it('hides approve/reject from an unrelated branch (Branch C)', function () {
        $this->actingAs($this->unrelatedAdmin)
            ->post(route('expenses.approve', $this->expense))
            ->assertForbidden();

        $this->actingAs($this->unrelatedAdmin)
            ->post(route('expenses.reject', $this->expense))
            ->assertForbidden();
    });
});

// ─── SCENARIO 3: Credit expense — Branch A = creditor, Branch B = debtor, created by Branch B (the debtor) ───
describe('Credit expense: creditor=BranchA, debtor=BranchB, created by BranchB', function () {
    beforeEach(function () {
        // Branch B user creates the expense (act as debtor)
        $debtorUser = User::factory()->create([
            'branch_id' => $this->branchB->id,
            'role' => 'admin',
        ]);

        $this->expense = Expense::factory()
            ->pending()
            ->credit()
            ->forBranch($this->branchB)
            ->createdBy($debtorUser)
            ->crossBranchCredit($this->branchA, $this->branchB)
            ->create(['amount' => 1500]);
    });

    it('hides approve/reject from the creator/debtor branch (Branch B)', function () {
        $debtorAdmin = User::factory()->create([
            'branch_id' => $this->branchB->id,
            'role' => 'admin',
        ]);

        $this->actingAs($debtorAdmin)
            ->post(route('expenses.approve', $this->expense))
            ->assertForbidden();

        $this->actingAs($debtorAdmin)
            ->post(route('expenses.reject', $this->expense))
            ->assertForbidden();
    });

    it('shows approve/reject to the creditor branch when they did NOT create the expense (Branch A)', function () {
        $this->actingAs($this->creator) // creator is from Branch A but did NOT create this expense
            ->post(route('expenses.approve', $this->expense))
            ->assertSessionHasNoErrors();

        $debtorUser = User::factory()->create([
            'branch_id' => $this->branchB->id,
            'role' => 'admin',
        ]);

        $expense2 = Expense::factory()
            ->pending()
            ->credit()
            ->forBranch($this->branchB)
            ->createdBy($debtorUser)
            ->crossBranchCredit($this->branchA, $this->branchB)
            ->create(['amount' => 1500]);

        $this->actingAs($this->creator)
            ->post(route('expenses.reject', $expense2))
            ->assertSessionHasNoErrors();
    });
});
