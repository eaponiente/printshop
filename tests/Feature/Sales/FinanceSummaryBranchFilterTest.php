<?php

use App\Models\Branch;
use App\Models\Expense;
use App\Models\Transaction;
use App\Models\User;

beforeEach(function () {
    $this->branchA = Branch::factory()->create(['name' => 'Branch A']);
    $this->branchB = Branch::factory()->create(['name' => 'Branch B']);

    $this->superadmin = User::factory()->create([
        'branch_id' => null,
        'role' => 'superadmin',
    ]);

    $this->adminA = User::factory()->create([
        'branch_id' => $this->branchA->id,
        'role' => 'admin',
    ]);

    $this->staffA = User::factory()->create([
        'branch_id' => $this->branchA->id,
        'role' => 'staff',
    ]);
});

// ── Superadmin: sees total expenses from all branches ──────────
it('superadmin sees total_expenses from all branches in finance summary', function () {
    Expense::factory()->paid()->cash()->forBranch($this->branchA)->createdBy($this->adminA)
        ->create(['amount' => 100, 'expense_date' => now()]);
    Expense::factory()->paid()->cash()->forBranch($this->branchB)->createdBy($this->adminA)
        ->create(['amount' => 200, 'expense_date' => now()]);

    $response = $this->actingAs($this->superadmin)
        ->get(route('sales.index', ['date' => now()->toDateString(), 'mode' => 'daily']));

    $response->assertOk();
    $totalExpenses = $response->inertiaProps('total_expenses');

    expect($totalExpenses)->toEqual(300.0);
});

// ── Superadmin: can filter expenses in finance summary by branch ─
it('superadmin can filter finance summary expenses by branch', function () {
    Expense::factory()->paid()->cash()->forBranch($this->branchA)->createdBy($this->adminA)
        ->create(['amount' => 100, 'expense_date' => now()]);
    Expense::factory()->paid()->cash()->forBranch($this->branchB)->createdBy($this->adminA)
        ->create(['amount' => 200, 'expense_date' => now()]);

    $response = $this->actingAs($this->superadmin)
        ->get(route('sales.index', [
            'date' => now()->toDateString(),
            'mode' => 'daily',
            'branch_id' => $this->branchA->id,
        ]));

    $response->assertOk();
    $totalExpenses = $response->inertiaProps('total_expenses');

    expect($totalExpenses)->toEqual(100.0);
});

it('superadmin sees all expenses when branch filter is "all"', function () {
    Expense::factory()->paid()->cash()->forBranch($this->branchA)->createdBy($this->adminA)
        ->create(['amount' => 100, 'expense_date' => now()]);
    Expense::factory()->paid()->cash()->forBranch($this->branchB)->createdBy($this->adminA)
        ->create(['amount' => 200, 'expense_date' => now()]);

    $response = $this->actingAs($this->superadmin)
        ->get(route('sales.index', [
            'date' => now()->toDateString(),
            'mode' => 'daily',
            'branch_id' => 'all',
        ]));

    $response->assertOk();
    $totalExpenses = $response->inertiaProps('total_expenses');

    expect($totalExpenses)->toEqual(300.0);
});

// ── Admin: finance summary restricted to own branch ─────────────
it('admin only sees own branch expenses in finance summary', function () {
    Expense::factory()->paid()->cash()->forBranch($this->branchA)->createdBy($this->adminA)
        ->create(['amount' => 100, 'expense_date' => now()]);
    Expense::factory()->paid()->cash()->forBranch($this->branchB)->createdBy($this->adminA)
        ->create(['amount' => 200, 'expense_date' => now()]);

    $response = $this->actingAs($this->adminA)
        ->get(route('sales.index', ['date' => now()->toDateString(), 'mode' => 'daily']));

    $response->assertOk();
    $totalExpenses = $response->inertiaProps('total_expenses');

    expect($totalExpenses)->toEqual(100.0);
});

// ── Staff: finance summary restricted to own expenses ───────────
it('staff only sees own expenses in finance summary', function () {
    Expense::factory()->paid()->cash()->forBranch($this->branchA)->createdBy($this->staffA)
        ->create(['amount' => 100, 'expense_date' => now()]);
    Expense::factory()->paid()->cash()->forBranch($this->branchA)->createdBy($this->adminA)
        ->create(['amount' => 200, 'expense_date' => now()]);

    $response = $this->actingAs($this->staffA)
        ->get(route('sales.index', ['date' => now()->toDateString(), 'mode' => 'daily']));

    $response->assertOk();
    $totalExpenses = $response->inertiaProps('total_expenses');

    expect($totalExpenses)->toEqual(100.0);
});

// ── Net income respects branch filtering ────────────────────────
it('calculates net_income respecting role-based expense filtering', function () {
    $tx = Transaction::factory()->create([
        'branch_id' => $this->branchA->id,
        'amount_total' => 500,
    ]);
    $tx->recordPayment(500, 'cash');

    Expense::factory()->paid()->cash()->forBranch($this->branchA)->createdBy($this->adminA)
        ->create(['amount' => 100, 'expense_date' => now()]);
    Expense::factory()->paid()->cash()->forBranch($this->branchB)->createdBy($this->adminA)
        ->create(['amount' => 200, 'expense_date' => now()]);

    $response = $this->actingAs($this->adminA)
        ->get(route('sales.index', ['date' => now()->toDateString(), 'mode' => 'daily']));

    $response->assertOk();
    $netIncome = $response->inertiaProps('net_income');

    expect($netIncome)->toEqual(400.0);
});
