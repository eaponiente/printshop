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

// ── cash_net_amount = cash payments - cash expenses ──────────────
it('computes cash_net_amount as cash payments minus cash expenses', function () {
    $tx = Transaction::factory()->create([
        'branch_id' => $this->branchA->id,
        'amount_total' => 1000,
    ]);
    $tx->recordPayment(1000, 'cash');

    Expense::factory()->paid()->cash()->forBranch($this->branchA)->createdBy($this->adminA)
        ->create(['amount' => 300, 'expense_date' => now()]);

    $response = $this->actingAs($this->superadmin)
        ->get(route('sales.index', ['date' => now()->toDateString(), 'mode' => 'daily']));

    $response->assertOk();
    $cashAmount = $response->inertiaProps('cash_amount');
    $cashNet = $response->inertiaProps('cash_net_amount');

    expect($cashAmount)->toEqual(1000.0);
    expect($cashNet)->toEqual(700.0);
});

// ── gcash_net_amount = gcash payments - gcash expenses ───────────
it('computes gcash_net_amount as gcash payments minus gcash expenses', function () {
    $tx = Transaction::factory()->create([
        'branch_id' => $this->branchA->id,
        'amount_total' => 500,
    ]);
    $tx->recordPayment(500, 'gcash');

    Expense::factory()->paid()->create([
        'payment_type' => 'gcash',
        'amount' => 150,
        'branch_id' => $this->branchA->id,
        'user_id' => $this->adminA->id,
        'expense_date' => now(),
    ]);

    $response = $this->actingAs($this->superadmin)
        ->get(route('sales.index', ['date' => now()->toDateString(), 'mode' => 'daily']));

    $response->assertOk();
    $gcashAmount = $response->inertiaProps('gcash_amount');
    $gcashNet = $response->inertiaProps('gcash_net_amount');

    expect($gcashAmount)->toEqual(500.0);
    expect($gcashNet)->toEqual(350.0);
});

// ── Only PAID expenses are subtracted ────────────────────────────
it('only subtracts paid expenses from net amounts', function () {
    $tx = Transaction::factory()->create([
        'branch_id' => $this->branchA->id,
        'amount_total' => 1000,
    ]);
    $tx->recordPayment(1000, 'cash');

    Expense::factory()->paid()->cash()->forBranch($this->branchA)->createdBy($this->adminA)
        ->create(['amount' => 200, 'expense_date' => now()]);
    Expense::factory()->pending()->cash()->forBranch($this->branchA)->createdBy($this->adminA)
        ->create(['amount' => 500, 'expense_date' => now()]);
    Expense::factory()->rejected()->cash()->forBranch($this->branchA)->createdBy($this->adminA)
        ->create(['amount' => 300, 'expense_date' => now()]);

    $response = $this->actingAs($this->superadmin)
        ->get(route('sales.index', ['date' => now()->toDateString(), 'mode' => 'daily']));

    $response->assertOk();
    $cashNet = $response->inertiaProps('cash_net_amount');

    expect($cashNet)->toEqual(800.0);
});

// ── Only subtracts expenses of matching payment type ─────────────
it('only subtracts expenses matching the payment type', function () {
    $tx = Transaction::factory()->create([
        'branch_id' => $this->branchA->id,
        'amount_total' => 1000,
    ]);
    $tx->recordPayment(1000, 'cash');

    Expense::factory()->paid()->cash()->forBranch($this->branchA)->createdBy($this->adminA)
        ->create(['amount' => 200, 'expense_date' => now()]);
    Expense::factory()->paid()->credit()->forBranch($this->branchA)->createdBy($this->adminA)
        ->create(['amount' => 300, 'expense_date' => now()]);

    $response = $this->actingAs($this->superadmin)
        ->get(route('sales.index', ['date' => now()->toDateString(), 'mode' => 'daily']));

    $response->assertOk();
    $cashNet = $response->inertiaProps('cash_net_amount');

    expect($cashNet)->toEqual(800.0);
});

// ── Net amounts respect branch filtering for superadmin ──────────
it('superadmin cash net filter respects branch_id', function () {
    $txA = Transaction::factory()->create([
        'branch_id' => $this->branchA->id,
        'amount_total' => 1000,
    ]);
    $txA->recordPayment(1000, 'cash');

    Expense::factory()->paid()->cash()->forBranch($this->branchA)->createdBy($this->adminA)
        ->create(['amount' => 100, 'expense_date' => now()]);
    Expense::factory()->paid()->cash()->forBranch($this->branchB)->createdBy($this->adminA)
        ->create(['amount' => 300, 'expense_date' => now()]);

    $response = $this->actingAs($this->superadmin)
        ->get(route('sales.index', [
            'date' => now()->toDateString(),
            'mode' => 'daily',
            'branch_id' => $this->branchA->id,
        ]));

    $response->assertOk();
    $cashNet = $response->inertiaProps('cash_net_amount');

    expect($cashNet)->toEqual(900.0);
});

// ── Net amounts respect branch filtering for admin ───────────────
it('admin cash net only uses own branch expenses', function () {
    $tx = Transaction::factory()->create([
        'branch_id' => $this->branchA->id,
        'amount_total' => 1000,
    ]);
    $tx->recordPayment(1000, 'cash');

    Expense::factory()->paid()->cash()->forBranch($this->branchA)->createdBy($this->adminA)
        ->create(['amount' => 200, 'expense_date' => now()]);
    Expense::factory()->paid()->cash()->forBranch($this->branchB)->createdBy($this->adminA)
        ->create(['amount' => 500, 'expense_date' => now()]);

    $response = $this->actingAs($this->adminA)
        ->get(route('sales.index', ['date' => now()->toDateString(), 'mode' => 'daily']));

    $response->assertOk();
    $cashNet = $response->inertiaProps('cash_net_amount');

    expect($cashNet)->toEqual(800.0);
});

// ── Net amounts respect filtering for staff ──────────────────────
it('staff cash net only uses own expenses', function () {
    $tx = Transaction::factory()->create([
        'branch_id' => $this->branchA->id,
        'staff_id' => $this->staffA->id,
        'amount_total' => 1000,
    ]);
    $tx->recordPayment(1000, 'cash');

    Expense::factory()->paid()->cash()->forBranch($this->branchA)->createdBy($this->staffA)
        ->create(['amount' => 100, 'expense_date' => now()]);
    Expense::factory()->paid()->cash()->forBranch($this->branchA)->createdBy($this->adminA)
        ->create(['amount' => 300, 'expense_date' => now()]);

    $response = $this->actingAs($this->staffA)
        ->get(route('sales.index', ['date' => now()->toDateString(), 'mode' => 'daily']));

    $response->assertOk();
    $cashNet = $response->inertiaProps('cash_net_amount');

    expect($cashNet)->toEqual(900.0);
});

// ── Net amount is zero when no payments exist ────────────────────
it('returns zero cash net amount when no cash payments exist', function () {
    $response = $this->actingAs($this->superadmin)
        ->get(route('sales.index', ['date' => now()->toDateString(), 'mode' => 'daily']));

    $response->assertOk();
    $cashNet = $response->inertiaProps('cash_net_amount');
    $gcashNet = $response->inertiaProps('gcash_net_amount');

    expect($cashNet)->toEqual(0.0);
    expect($gcashNet)->toEqual(0.0);
});

// ── Net amounts absent from unpaid tab ───────────────────────────
it('does not include cash_net_amount on unpaid tab', function () {
    $response = $this->actingAs($this->superadmin)
        ->get(route('sales.index', ['tab' => 'unpaid']));

    $response->assertOk();
    $response->assertInertia(function ($page) {
        $page->missing('cash_net_amount')
            ->missing('gcash_net_amount');
    });
});

// ── Multiple cash/gcash payments summed correctly ────────────────
it('sums multiple cash payments for cash_net_amount', function () {
    $tx1 = Transaction::factory()->create([
        'branch_id' => $this->branchA->id,
        'amount_total' => 500,
    ]);
    $tx1->recordPayment(500, 'cash');

    $tx2 = Transaction::factory()->create([
        'branch_id' => $this->branchA->id,
        'amount_total' => 300,
    ]);
    $tx2->recordPayment(300, 'cash');

    Expense::factory()->paid()->cash()->forBranch($this->branchA)->createdBy($this->adminA)
        ->create(['amount' => 200, 'expense_date' => now()]);

    $response = $this->actingAs($this->superadmin)
        ->get(route('sales.index', ['date' => now()->toDateString(), 'mode' => 'daily']));

    $response->assertOk();
    $cashAmount = $response->inertiaProps('cash_amount');
    $cashNet = $response->inertiaProps('cash_net_amount');

    expect($cashAmount)->toEqual(800.0);
    expect($cashNet)->toEqual(600.0);
});
