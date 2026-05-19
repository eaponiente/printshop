<?php

use App\Enums\Incentives\IncentiveStatus;
use App\Models\Branch;
use App\Models\CashOnHand;
use App\Models\Expense;
use App\Models\Incentive;
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

it('superadmin can view incentives index', function () {
    $response = $this->actingAs($this->superadmin)
        ->get(route('incentives.index'));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page->component('incentives/list'));
});

it('admin cannot view incentives index', function () {
    $response = $this->actingAs($this->adminA)
        ->get(route('incentives.index'));

    $response->assertForbidden();
});

it('staff cannot view incentives index', function () {
    $response = $this->actingAs($this->staffA)
        ->get(route('incentives.index'));

    $response->assertForbidden();
});

it('superadmin sees all admins across branches', function () {
    $response = $this->actingAs($this->superadmin)
        ->get(route('incentives.index'));

    $response->assertOk();
    $incentives = $response->inertiaProps('incentives');

    expect($incentives)->toHaveCount(2);
});

it('lists multiple admins for the same branch', function () {
    $adminA2 = User::factory()->create([
        'branch_id' => $this->branchA->id,
        'role' => 'admin',
    ]);

    $response = $this->actingAs($this->superadmin)
        ->get(route('incentives.index', ['branch_id' => $this->branchA->id]));

    $response->assertOk();
    $incentives = $response->inertiaProps('incentives');

    expect($incentives)->toHaveCount(2);

    $managerIds = collect($incentives)->pluck('manager_id')->toArray();
    expect($managerIds)->toContain($this->adminA->id);
    expect($managerIds)->toContain($adminA2->id);
});

it('calculates correct net income per branch', function () {
    $tx = Transaction::factory()->create([
        'branch_id' => $this->branchA->id,
        'amount_total' => 10000,
    ]);
    $tx->recordPayment(10000, 'cash');

    Expense::factory()->paid()->cash()->forBranch($this->branchA)->createdBy($this->adminA)
        ->create(['amount' => 2000, 'expense_date' => now()]);

    $response = $this->actingAs($this->superadmin)
        ->get(route('incentives.index', [
            'branch_id' => $this->branchA->id,
            'month' => now()->month,
            'year' => now()->year,
        ]));

    $response->assertOk();
    $incentives = $response->inertiaProps('incentives');
    $branchIncentive = collect($incentives)->firstWhere('branch_id', $this->branchA->id);

    expect($branchIncentive['net_income'])->toEqual(8000.0);
    expect($branchIncentive['incentive_amount'])->toEqual(0);
});

it('shows zero incentive when net income is negative', function () {
    Expense::factory()->paid()->cash()->forBranch($this->branchA)->createdBy($this->adminA)
        ->create(['amount' => 5000, 'expense_date' => now()]);

    $response = $this->actingAs($this->superadmin)
        ->get(route('incentives.index', [
            'branch_id' => $this->branchA->id,
            'month' => now()->month,
            'year' => now()->year,
        ]));

    $response->assertOk();
    $incentives = $response->inertiaProps('incentives');
    $branchIncentive = collect($incentives)->firstWhere('branch_id', $this->branchA->id);

    expect($branchIncentive['net_income'])->toEqual(-5000.0);
    expect($branchIncentive['incentive_amount'])->toEqual(0);
});

it('superadmin can pay an incentive to a specific admin', function () {
    $tx = Transaction::factory()->create([
        'branch_id' => $this->branchA->id,
        'amount_total' => 10000,
    ]);
    $tx->recordPayment(10000, 'cash');

    $response = $this->actingAs($this->superadmin)
        ->post(route('incentives.pay'), [
            'branch_id' => $this->branchA->id,
            'user_id' => $this->adminA->id,
            'month' => now()->month,
            'year' => now()->year,
            'incentive_amount' => 350,
        ]);

    $response->assertRedirect();

    $incentive = Incentive::where('branch_id', $this->branchA->id)
        ->where('user_id', $this->adminA->id)
        ->where('month', now()->month)
        ->where('year', now()->year)
        ->first();

    expect($incentive)->not->toBeNull();
    expect($incentive->status)->toEqual(IncentiveStatus::PAID->value);
    expect((float) $incentive->incentive_amount)->toEqual(350.0);
    expect($incentive->paid_at)->not->toBeNull();
});

it('can pay multiple admins in the same branch separately', function () {
    $adminA2 = User::factory()->create([
        'branch_id' => $this->branchA->id,
        'role' => 'admin',
    ]);

    $tx = Transaction::factory()->create([
        'branch_id' => $this->branchA->id,
        'amount_total' => 20000,
    ]);
    $tx->recordPayment(20000, 'cash');

    $this->actingAs($this->superadmin)
        ->post(route('incentives.pay'), [
            'branch_id' => $this->branchA->id,
            'user_id' => $this->adminA->id,
            'month' => now()->month,
            'year' => now()->year,
            'incentive_amount' => 500,
        ]);

    $this->actingAs($this->superadmin)
        ->post(route('incentives.pay'), [
            'branch_id' => $this->branchA->id,
            'user_id' => $adminA2->id,
            'month' => now()->month,
            'year' => now()->year,
            'incentive_amount' => 300,
        ]);

    expect(Incentive::count())->toBe(2);

    $inc1 = Incentive::where('user_id', $this->adminA->id)->first();
    expect((float) $inc1->incentive_amount)->toEqual(500.0);

    $inc2 = Incentive::where('user_id', $adminA2->id)->first();
    expect((float) $inc2->incentive_amount)->toEqual(300.0);
});

it('incentive_amount is required when paying', function () {
    $response = $this->actingAs($this->superadmin)
        ->post(route('incentives.pay'), [
            'branch_id' => $this->branchA->id,
            'user_id' => $this->adminA->id,
            'month' => now()->month,
            'year' => now()->year,
        ]);

    $response->assertSessionHasErrors('incentive_amount');
});

it('user_id is required when paying', function () {
    $response = $this->actingAs($this->superadmin)
        ->post(route('incentives.pay'), [
            'branch_id' => $this->branchA->id,
            'month' => now()->month,
            'year' => now()->year,
            'incentive_amount' => 500,
        ]);

    $response->assertSessionHasErrors('user_id');
});

it('admin cannot pay an incentive', function () {
    $response = $this->actingAs($this->adminA)
        ->post(route('incentives.pay'), [
            'branch_id' => $this->branchA->id,
            'user_id' => $this->adminA->id,
            'month' => now()->month,
            'year' => now()->year,
            'incentive_amount' => 500,
        ]);

    $response->assertForbidden();
});

it('superadmin can filter incentives by branch', function () {
    $response = $this->actingAs($this->superadmin)
        ->get(route('incentives.index', [
            'branch_id' => $this->branchA->id,
        ]));

    $response->assertOk();
    $incentives = $response->inertiaProps('incentives');

    expect($incentives)->toHaveCount(1);
    expect($incentives[0]['branch_id'])->toEqual($this->branchA->id);
});

it('shows paid status for previously paid incentives', function () {
    Incentive::factory()->paid()->forBranch($this->branchA)->forUser($this->adminA)
        ->create([
            'month' => now()->month,
            'year' => now()->year,
            'net_income' => 10000,
            'incentive_amount' => 500,
        ]);

    $response = $this->actingAs($this->superadmin)
        ->get(route('incentives.index', [
            'branch_id' => $this->branchA->id,
            'month' => now()->month,
            'year' => now()->year,
        ]));

    $response->assertOk();
    $incentives = $response->inertiaProps('incentives');
    $branchIncentive = collect($incentives)->firstWhere('manager_id', $this->adminA->id);

    expect($branchIncentive['status'])->toEqual('paid');
});

it('records owner_contribution when cash on hand is insufficient', function () {
    $tx = Transaction::factory()->create([
        'branch_id' => $this->branchA->id,
        'amount_total' => 10000,
    ]);
    $tx->recordPayment(10000, 'cash');

    CashOnHand::factory()->create([
        'branch_id' => $this->branchA->id,
        'amount' => 200,
    ]);

    $response = $this->actingAs($this->superadmin)
        ->post(route('incentives.pay'), [
            'branch_id' => $this->branchA->id,
            'user_id' => $this->adminA->id,
            'month' => now()->month,
            'year' => now()->year,
            'incentive_amount' => 500,
        ]);

    $response->assertRedirect();

    $incentive = Incentive::where('branch_id', $this->branchA->id)
        ->where('user_id', $this->adminA->id)
        ->where('month', now()->month)
        ->where('year', now()->year)
        ->first();

    expect((float) $incentive->owner_contribution)->toEqual(300.0);

    $cashOnHand = CashOnHand::where('branch_id', $this->branchA->id)->first();
    expect((float) $cashOnHand->amount)->toEqual(0.0);
});

it('records zero owner_contribution when cash on hand covers incentive', function () {
    $tx = Transaction::factory()->create([
        'branch_id' => $this->branchA->id,
        'amount_total' => 20000,
    ]);
    $tx->recordPayment(20000, 'cash');

    CashOnHand::factory()->create([
        'branch_id' => $this->branchA->id,
        'amount' => 1000,
    ]);

    $response = $this->actingAs($this->superadmin)
        ->post(route('incentives.pay'), [
            'branch_id' => $this->branchA->id,
            'user_id' => $this->adminA->id,
            'month' => now()->month,
            'year' => now()->year,
            'incentive_amount' => 500,
        ]);

    $response->assertRedirect();

    $incentive = Incentive::where('branch_id', $this->branchA->id)
        ->where('user_id', $this->adminA->id)
        ->where('month', now()->month)
        ->where('year', now()->year)
        ->first();

    expect((float) $incentive->owner_contribution)->toEqual(0.0);

    $cashOnHand = CashOnHand::where('branch_id', $this->branchA->id)->first();
    expect((float) $cashOnHand->amount)->toEqual(500.0);
});
