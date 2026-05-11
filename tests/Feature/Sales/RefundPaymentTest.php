<?php

use App\Models\Branch;
use App\Models\CashOnHand;
use App\Models\Transaction;
use App\Models\User;

it('only allows refunds for non-pending transactions', function () {
    $branch = Branch::factory()->create();
    $transaction = Transaction::factory()->create([
        'amount_total' => 100,
        'amount_paid' => 100,
        'status' => 'paid',
        'branch_id' => $branch->id,
    ]);

    $user = User::factory()->create([
        'branch_id' => $branch->id,
        'role' => 'staff',
    ]);

    $this->actingAs($user)
        ->patch(route('sales.refund-payment', $transaction), [
            'payment_type' => 'check',
        ])
        ->assertSessionHasNoErrors(['payment_type']);
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
