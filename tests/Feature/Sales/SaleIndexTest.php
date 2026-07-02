<?php

use App\Models\Branch;
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

    $this->adminB = User::factory()->create([
        'branch_id' => $this->branchB->id,
        'role' => 'admin',
    ]);

    $this->staffA = User::factory()->create([
        'branch_id' => $this->branchA->id,
        'role' => 'staff',
    ]);
});

// ── Access ──────────────────────────────────────────────────────
it('allows superadmin to access the sale index', function () {
    $this->actingAs($this->superadmin)
        ->get(route('sales.index'))
        ->assertOk();
});

it('allows admin to access the sale index', function () {
    $this->actingAs($this->adminA)
        ->get(route('sales.index'))
        ->assertOk();
});

it('allows staff to access the sale index', function () {
    $this->actingAs($this->staffA)
        ->get(route('sales.index'))
        ->assertOk();
});

// ── Role: Superadmin sees all branches ──────────────────────────
it('superadmin sees transactions from all branches in paid tab', function () {
    $txA = Transaction::factory()->create([
        'branch_id' => $this->branchA->id,
        'amount_total' => 100,
    ]);
    $txA->recordPayment(100, 'cash');

    $txB = Transaction::factory()->create([
        'branch_id' => $this->branchB->id,
        'amount_total' => 200,
    ]);
    $txB->recordPayment(200, 'cash');

    $response = $this->actingAs($this->superadmin)
        ->get(route('sales.index', ['tab' => 'paid', 'date' => now()->toDateString(), 'mode' => 'daily']));

    $response->assertOk();
    $transactions = $response->inertiaProps('transactions');

    $data = $transactions['data'];
    $branchIds = collect($data)->pluck('branch_id')->unique()->filter()->values();

    expect($branchIds->toArray())->toEqualCanonicalizing([$this->branchA->id, $this->branchB->id]);
});

it('superadmin sees transactions from all branches in unpaid tab', function () {
    // These are pending with no payments
    Transaction::factory()->create([
        'branch_id' => $this->branchA->id,
        'amount_paid' => 0,
        'status' => 'pending',
    ]);
    Transaction::factory()->create([
        'branch_id' => $this->branchB->id,
        'amount_paid' => 0,
        'status' => 'pending',
    ]);

    $response = $this->actingAs($this->superadmin)
        ->get(route('sales.index', ['tab' => 'unpaid', 'date' => now()->toDateString(), 'mode' => 'daily']));

    $response->assertOk();
    $transactions = $response->inertiaProps('transactions');
    $data = $transactions['data'];
    $branchIds = collect($data)->pluck('branch_id')->unique()->filter()->values();

    expect($branchIds->toArray())->toEqualCanonicalizing([$this->branchA->id, $this->branchB->id]);
});

// ── Role: Admin is scoped to own branch ─────────────────────────
it('admin only sees their own branch in branch list', function () {
    $response = $this->actingAs($this->adminA)
        ->get(route('sales.index'));

    $response->assertOk();
    $branches = $response->inertiaProps('branches');

    expect($branches)->toHaveCount(1);
    expect($branches[0]['id'])->toBe($this->branchA->id);
});

it('admin does not see other branch transactions in paid tab', function () {
    $txA = Transaction::factory()->create([
        'branch_id' => $this->branchA->id,
        'amount_total' => 100,
    ]);
    $txA->recordPayment(100, 'cash');

    $txB = Transaction::factory()->create([
        'branch_id' => $this->branchB->id,
        'amount_total' => 200,
    ]);
    $txB->recordPayment(200, 'cash');

    $response = $this->actingAs($this->adminA)
        ->get(route('sales.index', ['tab' => 'paid', 'date' => now()->toDateString(), 'mode' => 'daily']));

    $response->assertOk();
    $transactions = $response->inertiaProps('transactions');
    $data = $transactions['data'];

    foreach ($data as $item) {
        expect($item['branch_id'])->toBe($this->branchA->id);
    }
});

it('admin does not see other branch transactions in unpaid tab', function () {
    Transaction::factory()->create([
        'branch_id' => $this->branchA->id,
        'amount_paid' => 0,
        'status' => 'pending',
    ]);
    Transaction::factory()->create([
        'branch_id' => $this->branchB->id,
        'amount_paid' => 0,
        'status' => 'pending',
    ]);

    $response = $this->actingAs($this->adminA)
        ->get(route('sales.index', ['tab' => 'unpaid', 'date' => now()->toDateString(), 'mode' => 'daily']));

    $response->assertOk();
    $transactions = $response->inertiaProps('transactions');
    $data = $transactions['data'];

    foreach ($data as $item) {
        expect($item['branch_id'])->toBe($this->branchA->id);
    }
});

// ── Role: Staff is scoped to own transactions ───────────────────
it('staff only sees their own branch in branch list', function () {
    $response = $this->actingAs($this->staffA)
        ->get(route('sales.index'));

    $response->assertOk();
    $branches = $response->inertiaProps('branches');

    expect($branches)->toHaveCount(1);
    expect($branches[0]['id'])->toBe($this->branchA->id);
});

it('staff only sees their own transactions in paid tab', function () {
    $txA = Transaction::factory()->create([
        'branch_id' => $this->branchA->id,
        'staff_id' => $this->staffA->id,
        'amount_total' => 100,
    ]);
    $txA->recordPayment(100, 'cash');

    $txB = Transaction::factory()->create([
        'branch_id' => $this->branchA->id,
        'staff_id' => $this->adminA->id,
        'amount_total' => 200,
    ]);
    $txB->recordPayment(200, 'cash');

    $response = $this->actingAs($this->staffA)
        ->get(route('sales.index', ['tab' => 'paid', 'date' => now()->toDateString(), 'mode' => 'daily']));

    $response->assertOk();
    $transactions = $response->inertiaProps('transactions');
    $data = $transactions['data'];

    foreach ($data as $item) {
        expect($item['staff_id'])->toBe($this->staffA->id);
    }
});

it('staff only sees their own transactions in unpaid tab', function () {
    Transaction::factory()->create([
        'branch_id' => $this->branchA->id,
        'staff_id' => $this->staffA->id,
        'amount_paid' => 0,
        'status' => 'pending',
    ]);
    Transaction::factory()->create([
        'branch_id' => $this->branchA->id,
        'staff_id' => $this->adminA->id,
        'amount_paid' => 0,
        'status' => 'pending',
    ]);

    $response = $this->actingAs($this->staffA)
        ->get(route('sales.index', ['tab' => 'unpaid', 'date' => now()->toDateString(), 'mode' => 'daily']));

    $response->assertOk();
    $transactions = $response->inertiaProps('transactions');
    $data = $transactions['data'];

    foreach ($data as $item) {
        expect($item['staff_id'])->toBe($this->staffA->id);
    }
});

// ── Tab: unpaid shows only pending transactions ─────────────────
it('unpaid tab shows only pending transactions', function () {
    Transaction::factory()->create([
        'branch_id' => $this->branchA->id,
        'amount_paid' => 0,
        'status' => 'pending',
    ]);
    Transaction::factory()->create([
        'branch_id' => $this->branchA->id,
        'amount_paid' => 100,
        'status' => 'paid',
    ]);

    $response = $this->actingAs($this->superadmin)
        ->get(route('sales.index', ['tab' => 'unpaid', 'date' => now()->toDateString(), 'mode' => 'daily']));

    $response->assertOk();
    $transactions = $response->inertiaProps('transactions');
    $data = $transactions['data'];

    foreach ($data as $item) {
        expect($item['status'])->toBe('pending');
    }
});

// ── Branch filter works for superadmin ──────────────────────────
it('superadmin can filter by branch in paid tab', function () {
    $txA = Transaction::factory()->create([
        'branch_id' => $this->branchA->id,
        'amount_total' => 100,
    ]);
    $txA->recordPayment(100, 'cash');

    $txB = Transaction::factory()->create([
        'branch_id' => $this->branchB->id,
        'amount_total' => 200,
    ]);
    $txB->recordPayment(200, 'cash');

    $response = $this->actingAs($this->superadmin)
        ->get(route('sales.index', [
            'tab' => 'paid',
            'date' => now()->toDateString(),
            'mode' => 'daily',
            'branch_id' => $this->branchA->id,
        ]));

    $response->assertOk();
    $transactions = $response->inertiaProps('transactions');
    $data = $transactions['data'];

    foreach ($data as $item) {
        expect($item['branch_id'])->toBe($this->branchA->id);
    }
});

it('superadmin can filter by branch in unpaid tab', function () {
    Transaction::factory()->create([
        'branch_id' => $this->branchA->id,
        'amount_paid' => 0,
        'status' => 'pending',
    ]);
    Transaction::factory()->create([
        'branch_id' => $this->branchB->id,
        'amount_paid' => 0,
        'status' => 'pending',
    ]);

    $response = $this->actingAs($this->superadmin)
        ->get(route('sales.index', [
            'tab' => 'unpaid',
            'date' => now()->toDateString(),
            'mode' => 'daily',
            'branch_id' => $this->branchA->id,
        ]));

    $response->assertOk();
    $transactions = $response->inertiaProps('transactions');
    $data = $transactions['data'];

    foreach ($data as $item) {
        expect($item['branch_id'])->toBe($this->branchA->id);
    }
});

// ── Mode filtering ──────────────────────────────────────────────
it('filters transactions by monthly mode', function () {
    $thisMonth = Transaction::factory()->create([
        'branch_id' => $this->branchA->id,
        'amount_paid' => 0,
        'status' => 'pending',
        'transaction_date' => now()->startOfMonth(),
    ]);
    Transaction::factory()->create([
        'branch_id' => $this->branchA->id,
        'amount_paid' => 0,
        'status' => 'pending',
        'transaction_date' => now()->subMonth()->startOfMonth(),
    ]);

    $response = $this->actingAs($this->superadmin)
        ->get(route('sales.index', [
            'tab' => 'unpaid',
            'date' => now()->toDateString(),
            'mode' => 'monthly',
        ]));

    $response->assertOk();
    $transactions = $response->inertiaProps('transactions');
    $data = $transactions['data'];
    $ids = collect($data)->pluck('id');

    expect($ids)->toContain($thisMonth->id);
});

// ── Response structure ──────────────────────────────────────────
it('returns expected inertia props for the default partial tab including net amounts', function () {
    $response = $this->actingAs($this->superadmin)
        ->get(route('sales.index'));

    $response->assertOk();
    $response->assertInertia(function ($page) {
        $page->component('sales/list')
            ->has('filters')
            ->has('branches')
            ->has('transactions')
            ->has('types_of_payment')
            ->has('cash_on_hand_amount')
            ->has('cash_net_amount')
            ->has('gcash_net_amount')
            ->where('is_payment_view', false)
            ->where('show_summary', true);
    });
});

it('returns expected inertia props for unpaid tab without finance summary', function () {
    $response = $this->actingAs($this->superadmin)
        ->get(route('sales.index', ['tab' => 'unpaid']));

    $response->assertOk();
    $response->assertInertia(function ($page) {
        $page->component('sales/list')
            ->has('filters')
            ->has('branches')
            ->has('transactions')
            ->has('types_of_payment')
            ->has('cash_on_hand_amount')
            ->where('is_payment_view', false)
            ->where('show_summary', false)
            ->missing('total_expenses')
            ->missing('net_income');
    });
});

// ── Breakdown is fixed to Partial+Paid ──────────────────────────
it('shows the same partial+paid breakdown on the partial and paid tabs and none on unpaid', function () {
    // A fully paid transaction and a partially paid one, both today.
    $paid = Transaction::factory()->create([
        'branch_id' => $this->branchA->id,
        'amount_total' => 100,
    ]);
    $paid->recordPayment(100, 'cash');

    $partial = Transaction::factory()->create([
        'branch_id' => $this->branchA->id,
        'amount_total' => 200,
    ]);
    $partial->recordPayment(50, 'cash');

    $date = now()->toDateString();

    $partialTotal = $this->actingAs($this->superadmin)
        ->get(route('sales.index', ['tab' => 'partial', 'date' => $date, 'mode' => 'daily']))
        ->inertiaProps('total_sales');

    $paidTotal = $this->actingAs($this->superadmin)
        ->get(route('sales.index', ['tab' => 'paid', 'date' => $date, 'mode' => 'daily']))
        ->inertiaProps('total_sales');

    // 100 (paid) + 50 (partial) collected today, identical regardless of tab.
    expect((float) $partialTotal)->toBe(150.0)
        ->and((float) $paidTotal)->toBe(150.0);

    $this->actingAs($this->superadmin)
        ->get(route('sales.index', ['tab' => 'unpaid', 'date' => $date, 'mode' => 'daily']))
        ->assertInertia(fn ($page) => $page->where('show_summary', false)->missing('total_sales'));
});
