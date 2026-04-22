<?php

use App\Models\Transaction;
use App\Models\User;
use App\Models\Branch;

it('prevents overpayment', function () {
    $branch = Branch::factory()->create();
    $transaction = Transaction::factory()->create([
        'amount_total' => 100,
        'amount_paid' => 80,
        'branch_id' => $branch->id,
    ]);

    $user = User::factory()->create([
        'branch_id' => $branch->id,
        'role' => 'staff'
    ]);

    $this->actingAs($user)
        ->patchJson(route('sales.update-payment', $transaction), [
            'amount_paid' => 30, // 80 + 30 = 110 > 100
            'payment_type' => 'cash',
            'status' => 'partial',
        ])
        ->assertJsonValidationErrors(['amount_paid']);
});

it('restricts non-superadmin to their own branch data for payments', function () {
    $branchA = Branch::factory()->create();
    $branchB = Branch::factory()->create();

    $transaction = Transaction::factory()->create([
        'branch_id' => $branchA->id,
    ]);

    $user = User::factory()->create([
        'branch_id' => $branchB->id,
        'role' => 'staff'
    ]);

    $this->actingAs($user)
        ->patchJson(route('sales.update-payment', $transaction), [
            'amount_paid' => 10,
            'payment_type' => 'cash',
            'status' => 'partial',
        ])
        ->assertForbidden();
});

it('successfully records a valid payment', function () {
    $branch = Branch::factory()->create();
    $transaction = Transaction::factory()->create([
        'amount_total' => 100,
        'amount_paid' => 0,
        'branch_id' => $branch->id,
    ]);

    $user = User::factory()->create([
        'branch_id' => $branch->id,
        'role' => 'staff'
    ]);

    $this->actingAs($user)
        ->patchJson(route('sales.update-payment', $transaction), [
            'amount_paid' => 100,
            'payment_type' => 'cash',
            'status' => 'paid',
        ])
        ->assertSessionHasNoErrors();

    expect($transaction->fresh()->amount_paid)->toEqual(100)
        ->and($transaction->fresh()->status)->toEqual('paid');
});
