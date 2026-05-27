<?php

use App\Enums\Sublimations\SublimationStatus;
use App\Models\Branch;
use App\Models\Customer;
use App\Models\Sublimation;
use App\Models\User;

beforeEach(function () {
    $this->branch = Branch::factory()->create();
    $this->customer = Customer::factory()->create();
    $this->creator = User::factory()->create([
        'branch_id' => $this->branch->id,
        'role' => 'admin',
    ]);
    $this->assignee = User::factory()->create([
        'branch_id' => $this->branch->id,
        'role' => 'staff',
    ]);
    $this->superadmin = User::factory()->create([
        'branch_id' => null,
        'role' => 'superadmin',
    ]);
});

it('creates transaction with assigned user_id as staff_id when status changes to waiting_for_dp', function () {
    $sublimation = Sublimation::factory()->create([
        'branch_id' => $this->branch->id,
        'customer_id' => $this->customer->id,
        'user_id' => null,
        'status' => SublimationStatus::FOR_APPROVAL->value,
        'amount_total' => 1500,
        'quantity' => 1,
    ]);

    expect($sublimation->transaction_id)->toBeNull();
    expect($sublimation->user_id)->toBeNull();

    $sublimation->update(['user_id' => $this->assignee->id]);
    expect($sublimation->refresh()->user_id)->toBe($this->assignee->id);

    $this->actingAs($this->superadmin)
        ->patch(route('sublimations.update-status', $sublimation), [
            'status' => SublimationStatus::WAITING_FOR_DP->value,
        ])
        ->assertRedirect();

    $sublimation->refresh();

    expect($sublimation->status)->toBe(SublimationStatus::WAITING_FOR_DP);
    expect($sublimation->transaction_id)->not->toBeNull();

    $transaction = $sublimation->transaction;
    expect($transaction)->not->toBeNull();
    expect($transaction->staff_id)->toBe($this->assignee->id);
    expect($transaction->staff_id)->not->toBe($this->creator->id);
});
