<?php

use App\Enums\Expenses\ExpenseStatus;
use App\Models\Branch;
use App\Models\Expense;
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
it('allows superadmin to access the expense index', function () {
    $this->actingAs($this->superadmin)
        ->get(route('expenses.index'))
        ->assertOk();
});

it('allows admin to access the expense index', function () {
    $this->actingAs($this->adminA)
        ->get(route('expenses.index'))
        ->assertOk();
});

it('denies staff access to the expense index', function () {
    $this->actingAs($this->staffA)
        ->get(route('expenses.index'))
        ->assertForbidden();
});

// ── Role: Superadmin sees expenses from all branches ────────────
it('superadmin sees expenses from all branches', function () {
    Expense::factory()->paid()->forBranch($this->branchA)->createdBy($this->adminA)->create();
    Expense::factory()->paid()->forBranch($this->branchB)->createdBy($this->adminB)->create();

    $response = $this->actingAs($this->superadmin)
        ->get(route('expenses.index'));

    $response->assertOk();
    $expenses = $response->inertiaProps('expenses');
    $data = $expenses['data'];
    $branchIds = collect($data)->pluck('branch_id')->unique()->values();

    expect($branchIds->toArray())->toEqualCanonicalizing([$this->branchA->id, $this->branchB->id]);
});

// ── Role: Admin sees expenses from all branches too (no branch scope) ───
it('admin sees expenses from all branches', function () {
    Expense::factory()->paid()->forBranch($this->branchA)->createdBy($this->adminA)->create();
    Expense::factory()->paid()->forBranch($this->branchB)->createdBy($this->adminB)->create();

    $response = $this->actingAs($this->adminA)
        ->get(route('expenses.index'));

    $response->assertOk();
    $expenses = $response->inertiaProps('expenses');
    $data = $expenses['data'];
    $branchIds = collect($data)->pluck('branch_id')->unique()->values();

    expect($branchIds->toArray())->toEqualCanonicalizing([$this->branchA->id, $this->branchB->id]);
});

// ── Filter: by branch ───────────────────────────────────────────
it('filters expenses by branch_id', function () {
    Expense::factory()->paid()->forBranch($this->branchA)->createdBy($this->adminA)->create();
    Expense::factory()->paid()->forBranch($this->branchB)->createdBy($this->adminB)->create();

    $response = $this->actingAs($this->superadmin)
        ->get(route('expenses.index', ['branch_id' => $this->branchA->id]));

    $response->assertOk();
    $expenses = $response->inertiaProps('expenses');
    $data = $expenses['data'];

    foreach ($data as $item) {
        expect($item['branch_id'])->toBe($this->branchA->id);
    }
});

it('shows all branches when branch_id is "all"', function () {
    Expense::factory()->paid()->forBranch($this->branchA)->createdBy($this->adminA)->create();
    Expense::factory()->paid()->forBranch($this->branchB)->createdBy($this->adminB)->create();

    $response = $this->actingAs($this->superadmin)
        ->get(route('expenses.index', ['branch_id' => 'all']));

    $response->assertOk();
    $expenses = $response->inertiaProps('expenses');
    $data = $expenses['data'];
    $branchIds = collect($data)->pluck('branch_id')->unique()->values();

    expect($branchIds->toArray())->toEqualCanonicalizing([$this->branchA->id, $this->branchB->id]);
});

// ── Filter: by payment_type ─────────────────────────────────────
it('filters expenses by payment_type', function () {
    Expense::factory()->paid()->cash()->forBranch($this->branchA)->createdBy($this->adminA)->create();
    Expense::factory()->paid()->credit()->forBranch($this->branchA)->createdBy($this->adminA)->create();

    $response = $this->actingAs($this->superadmin)
        ->get(route('expenses.index', ['payment_type' => 'cash']));

    $response->assertOk();
    $expenses = $response->inertiaProps('expenses');
    $data = $expenses['data'];

    foreach ($data as $item) {
        expect($item['payment_type'])->toBe('cash');
    }
});

// ── expenses_amount: only sums PAID expenses ────────────────────
it('calculates expenses_amount as sum of paid expenses only', function () {
    Expense::factory()->paid()->cash()->forBranch($this->branchA)->createdBy($this->adminA)
        ->create(['amount' => 100]);
    Expense::factory()->paid()->cash()->forBranch($this->branchA)->createdBy($this->adminA)
        ->create(['amount' => 200]);
    Expense::factory()->pending()->cash()->forBranch($this->branchA)->createdBy($this->adminA)
        ->create(['amount' => 500]);

    $response = $this->actingAs($this->superadmin)
        ->get(route('expenses.index'));

    $response->assertOk();
    $expensesAmount = $response->inertiaProps('expenses_amount');

    expect($expensesAmount)->toEqual(300.0);
});

it('excludes voided and rejected expenses from expenses_amount', function () {
    Expense::factory()->paid()->cash()->forBranch($this->branchA)->createdBy($this->adminA)
        ->create(['amount' => 100]);
    Expense::factory()->rejected()->cash()->forBranch($this->branchA)->createdBy($this->adminA)
        ->create(['amount' => 300]);
    Expense::factory()->state(['status' => ExpenseStatus::VOID->value])->cash()
        ->forBranch($this->branchA)->createdBy($this->adminA)
        ->create(['amount' => 400]);

    $response = $this->actingAs($this->superadmin)
        ->get(route('expenses.index'));

    $response->assertOk();
    $expensesAmount = $response->inertiaProps('expenses_amount');

    expect($expensesAmount)->toEqual(100.0);
});

// ── Response structure ──────────────────────────────────────────
it('returns expected inertia props for expense index', function () {
    $response = $this->actingAs($this->superadmin)
        ->get(route('expenses.index'));

    $response->assertOk();
    $response->assertInertia(function ($page) {
        $page->component('expenses/list')
            ->has('filters')
            ->has('expenses_amount')
            ->has('expenses')
            ->has('branches')
            ->has('payment_methods');
    });
});

// ── Pagination ──────────────────────────────────────────────────
it('paginates expenses with 30 per page', function () {
    Expense::factory()->paid()->forBranch($this->branchA)->createdBy($this->adminA)
        ->count(35)->create();

    $response = $this->actingAs($this->superadmin)
        ->get(route('expenses.index'));

    $response->assertOk();
    $expenses = $response->inertiaProps('expenses');

    expect($expenses['per_page'])->toBe(30);
    expect(count($expenses['data']))->toBe(30);
});

// ── Eager loading ───────────────────────────────────────────────
it('eager loads branch and user.branch relations', function () {
    Expense::factory()->paid()->forBranch($this->branchA)->createdBy($this->adminA)->create();

    $response = $this->actingAs($this->superadmin)
        ->get(route('expenses.index'));

    $response->assertOk();
    $expenses = $response->inertiaProps('expenses');
    $item = $expenses['data'][0];

    expect($item)->toHaveKey('branch')
        ->and($item['user'])->toHaveKey('branch');
});
