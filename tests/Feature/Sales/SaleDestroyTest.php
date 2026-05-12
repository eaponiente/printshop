<?php

use App\Models\Branch;
use App\Models\Transaction;
use App\Models\User;

beforeEach(function () {
    $this->branch = Branch::factory()->create();
    $this->superadmin = User::factory()->create([
        'branch_id' => $this->branch->id,
        'role' => 'superadmin',
    ]);
});

it('allows superadmin to delete a fully refunded transaction', function () {
    $transaction = Transaction::factory()->create([
        'amount_total' => 100,
        'amount_paid' => 60,
        'status' => 'partial',
        'branch_id' => $this->branch->id,
    ]);

    $transaction->payments()->create([
        'amount' => 60,
        'payment_type' => 'cash',
        'staff_id' => $this->superadmin->id,
    ]);

    $this->actingAs($this->superadmin);

    $transaction->refundPayment();

    $this->delete(route('sales.destroy', $transaction->fresh()))
        ->assertRedirect()
        ->assertSessionHas('success', 'Sale deleted successfully.');
});

it('does not allow deletion of a transaction with payments', function () {
    $transaction = Transaction::factory()->create([
        'amount_total' => 100,
        'amount_paid' => 60,
        'status' => 'partial',
        'branch_id' => $this->branch->id,
    ]);

    $transaction->payments()->create([
        'amount' => 60,
        'payment_type' => 'cash',
        'staff_id' => $this->superadmin->id,
    ]);

    $this->actingAs($this->superadmin)
        ->delete(route('sales.destroy', $transaction))
        ->assertSessionHasErrors(['message']);
});

it('does not allow non-superadmin to delete any transaction', function () {
    $staff = User::factory()->create([
        'branch_id' => $this->branch->id,
        'role' => 'staff',
    ]);

    $transaction = Transaction::factory()->create([
        'branch_id' => $this->branch->id,
        'status' => 'pending',
        'amount_paid' => 0,
    ]);

    $this->actingAs($staff)
        ->delete(route('sales.destroy', $transaction))
        ->assertSessionHasErrors(['message']);
});
