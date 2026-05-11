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
        ->patch(route('sales.refund-payment', $transaction), [
            'payment_type' => 'check',
        ])
        ->assertSessionHasNoErrors();

    $refreshed = $transaction->fresh();
    $latestPayment = $refreshed->payments()->latest('id')->first();

    expect($refreshed->amount_paid)->toEqual(0.0)
        ->and($refreshed->status)->toEqual('pending')
        ->and($refreshed->fulfilled_at)->toBeNull()
        ->and($refreshed->payments()->whereIn('id', $existingPaymentIds)->count())->toEqual(count($existingPaymentIds))
        ->and((float) $latestPayment->amount)->toEqual(-100.0);
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
        ->patch(route('sales.refund-payment', $transaction), [
            'payment_type' => 'cash',
        ])
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
        ->patch(route('sales.refund-payment', $transaction), [
            'payment_type' => 'cash',
        ])
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

    $this->actingAs($user)
        ->patch(route('sales.refund-payment', $transaction), [
            'payment_type' => 'cash',
        ])
        ->assertSessionHasNoErrors();

    expect($transaction->fresh()->amount_paid)->toEqual(0.0)
        ->and($transaction->fresh()->status)->toEqual('pending')
        ->and((float) CashOnHand::query()->where('branch_id', $branch->id)->value('amount'))->toEqual(40.0)
        ->and((float) $transaction->fresh()->payments()->latest('id')->value('amount'))->toEqual(-60.0);
});
