<?php

use App\Models\Branch;
use App\Models\CashOnHand;
use App\Models\Transaction;
use App\Models\User;

it('fully refunds a paid transaction and resets it to pending', function () {
    $branch = Branch::factory()->create();
    $transaction = Transaction::factory()->create([
        'amount_total' => 100,
        'amount_paid' => 100,
        'status' => 'paid',
        'fulfilled_at' => now()->subDay(),
        'branch_id' => $branch->id,
    ]);

    $user = User::factory()->create([
        'branch_id' => $branch->id,
        'role' => 'superadmin',
    ]);

    $transaction->payments()->create([
        'amount' => 100,
        'payment_type' => 'cash',
        'staff_id' => $user->id,
    ]);

    $existingPaymentIds = $transaction->payments()->pluck('id')->all();

    $this->actingAs($user)
        ->patch(route('sales.refund-payment', $transaction))
        ->assertSessionHasNoErrors();

    $refreshed = $transaction->fresh();
    $latestPayment = $refreshed->payments()->latest('id')->first();

    expect($refreshed->amount_paid)->toEqual(0.0)
        ->and($refreshed->status)->toEqual('pending')
        ->and($refreshed->fulfilled_at)->toBeNull()
        ->and($refreshed->payments()->whereIn('id', $existingPaymentIds)->count())->toEqual(count($existingPaymentIds))
        ->and((float) $latestPayment->amount)->toEqual(-100.0)
        ->and($latestPayment->payment_type)->toEqual('cash');
});

it('allows an admin to refund a transaction in their own branch', function () {
    $branch = Branch::factory()->create();
    CashOnHand::create(['branch_id' => $branch->id, 'amount' => 100]);

    $transaction = Transaction::factory()->create([
        'amount_total' => 100,
        'amount_paid' => 100,
        'status' => 'paid',
        'branch_id' => $branch->id,
    ]);

    $admin = User::factory()->create([
        'branch_id' => $branch->id,
        'role' => 'admin',
    ]);

    $transaction->payments()->create([
        'amount' => 100,
        'payment_type' => 'cash',
        'staff_id' => $admin->id,
    ]);

    $this->actingAs($admin)
        ->patch(route('sales.refund-payment', $transaction))
        ->assertSessionHasNoErrors();

    expect($transaction->fresh()->amount_paid)->toEqual(0.0)
        ->and($transaction->fresh()->status)->toEqual('pending');
});

it('forbids an admin from refunding a transaction in another branch', function () {
    $branch = Branch::factory()->create();
    $otherBranch = Branch::factory()->create();

    $transaction = Transaction::factory()->create([
        'amount_total' => 100,
        'amount_paid' => 100,
        'status' => 'paid',
        'branch_id' => $otherBranch->id,
    ]);

    $admin = User::factory()->create([
        'branch_id' => $branch->id,
        'role' => 'admin',
    ]);

    $this->actingAs($admin)
        ->patch(route('sales.refund-payment', $transaction))
        ->assertForbidden();

    expect($transaction->fresh()->amount_paid)->toEqual(100.0)
        ->and($transaction->fresh()->status)->toEqual('paid');
});

it('rejects refund for pending transactions', function () {
    $branch = Branch::factory()->create();
    $transaction = Transaction::factory()->create([
        'amount_total' => 100,
        'amount_paid' => 0,
        'status' => 'pending',
        'branch_id' => $branch->id,
    ]);

    $user = User::factory()->create([
        'branch_id' => $branch->id,
        'role' => 'staff',
    ]);

    $this->actingAs($user)
        ->patch(route('sales.refund-payment', $transaction))
        ->assertSessionHasErrors(['payment_type']);
});

it('records a full refund as a ledger entry instead of deleting payments', function () {
    $branch = Branch::factory()->create();
    CashOnHand::create([
        'branch_id' => $branch->id,
        'amount' => 100,
    ]);

    $transaction = Transaction::factory()->create([
        'amount_total' => 100,
        'amount_paid' => 60,
        'status' => 'partial',
        'branch_id' => $branch->id,
    ]);

    $user = User::factory()->create([
        'branch_id' => $branch->id,
        'role' => 'staff',
    ]);

    $transaction->payments()->create([
        'amount' => 60,
        'payment_type' => 'cash',
        'staff_id' => $user->id,
    ]);

    $existingPaymentIds = $transaction->payments()->pluck('id')->all();

    $this->actingAs($user)
        ->patch(route('sales.refund-payment', $transaction))
        ->assertSessionHasNoErrors();

    $refreshed = $transaction->fresh();
    $latestPayment = $refreshed->payments()->latest('id')->first();

    expect($refreshed->amount_paid)->toEqual(0.0)
        ->and($refreshed->status)->toEqual('pending')
        ->and($refreshed->payments()->whereIn('id', $existingPaymentIds)->count())->toEqual(count($existingPaymentIds))
        ->and((float) $latestPayment->amount)->toEqual(-60.0);
});

it('deducts cash on hand when a cash full refund is recorded', function () {
    $branch = Branch::factory()->create();
    CashOnHand::create([
        'branch_id' => $branch->id,
        'amount' => 100,
    ]);

    $transaction = Transaction::factory()->create([
        'amount_total' => 100,
        'amount_paid' => 60,
        'status' => 'partial',
        'branch_id' => $branch->id,
    ]);

    $user = User::factory()->create([
        'branch_id' => $branch->id,
        'role' => 'staff',
    ]);

    $transaction->payments()->create([
        'amount' => 60,
        'payment_type' => 'cash',
        'staff_id' => $user->id,
    ]);

    $this->actingAs($user)
        ->patch(route('sales.refund-payment', $transaction))
        ->assertSessionHasNoErrors();

    expect($transaction->fresh()->amount_paid)->toEqual(0.0)
        ->and($transaction->fresh()->status)->toEqual('pending')
        ->and((float) CashOnHand::query()->where('branch_id', $branch->id)->value('amount'))->toEqual(40.0)
        ->and((float) $transaction->fresh()->payments()->latest('id')->value('amount'))->toEqual(-60.0);
});

it('does not adjust cash on hand for non-cash refunds', function () {
    $branch = Branch::factory()->create();
    CashOnHand::create([
        'branch_id' => $branch->id,
        'amount' => 100,
    ]);

    $transaction = Transaction::factory()->create([
        'amount_total' => 100,
        'amount_paid' => 60,
        'status' => 'partial',
        'branch_id' => $branch->id,
    ]);

    $user = User::factory()->create([
        'branch_id' => $branch->id,
        'role' => 'staff',
    ]);

    $transaction->payments()->create([
        'amount' => 60,
        'payment_type' => 'gcash',
        'staff_id' => $user->id,
    ]);

    $this->actingAs($user)
        ->patch(route('sales.refund-payment', $transaction))
        ->assertSessionHasNoErrors();

    expect($transaction->fresh()->amount_paid)->toEqual(0.0)
        ->and((float) CashOnHand::query()->where('branch_id', $branch->id)->value('amount'))->toEqual(100.0);
});

it('creates separate negative entries per payment type when refunding mixed payments', function () {
    $branch = Branch::factory()->create();
    CashOnHand::create([
        'branch_id' => $branch->id,
        'amount' => 100,
    ]);

    $transaction = Transaction::factory()->create([
        'amount_total' => 200,
        'amount_paid' => 120,
        'status' => 'partial',
        'branch_id' => $branch->id,
    ]);

    $user = User::factory()->create([
        'branch_id' => $branch->id,
        'role' => 'superadmin',
    ]);

    $transaction->payments()->createMany([
        ['amount' => 80, 'payment_type' => 'cash', 'staff_id' => $user->id],
        ['amount' => 40, 'payment_type' => 'gcash', 'staff_id' => $user->id],
    ]);

    $existingPaymentIds = $transaction->payments()->pluck('id')->all();

    $this->actingAs($user)
        ->patch(route('sales.refund-payment', $transaction))
        ->assertSessionHasNoErrors();

    $refreshed = $transaction->fresh();

    expect($refreshed->amount_paid)->toEqual(0.0)
        ->and($refreshed->status)->toEqual('pending')
        ->and($refreshed->payments()->whereIn('id', $existingPaymentIds)->count())->toEqual(count($existingPaymentIds));

    $negativeEntries = $refreshed->payments()->where('amount', '<', 0)->get();

    expect($negativeEntries)->toHaveCount(2)
        ->and($negativeEntries->where('payment_type', 'cash')->sum('amount'))->toEqual(-80.0)
        ->and($negativeEntries->where('payment_type', 'gcash')->sum('amount'))->toEqual(-40.0)
        ->and((float) CashOnHand::query()->where('branch_id', $branch->id)->value('amount'))->toEqual(20.0);
});
