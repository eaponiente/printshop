<?php

use App\Enums\PurchaseOrders\PurchaseOrderStatus;
use App\Models\Branch;
use App\Models\Customer;
use App\Models\PurchaseOrder;
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

it('creates transaction with assigned_user_id as staff_id when creating transaction from purchase order', function () {
    $purchaseOrder = new PurchaseOrder([
        'po_number' => 'TEST-PO-001',
        'branch_id' => $this->branch->id,
        'customer_id' => $this->customer->id,
        'user_id' => $this->creator->id,
        'assigned_user_id' => null,
        'grand_total' => 2000,
        'status' => PurchaseOrderStatus::PENDING->value,
    ]);
    $purchaseOrder->save();

    expect($purchaseOrder->transaction_id)->toBeNull();
    expect($purchaseOrder->assigned_user_id)->toBeNull();

    $purchaseOrder->update(['assigned_user_id' => $this->assignee->id]);
    expect($purchaseOrder->refresh()->assigned_user_id)->toBe($this->assignee->id);

    $this->actingAs($this->superadmin)
        ->post(route('purchase-orders.transactions.store', $purchaseOrder), [
            'amount_total' => 2000,
        ])
        ->assertRedirect();

    $purchaseOrder->refresh();

    expect($purchaseOrder->transaction_id)->not->toBeNull();

    $transaction = $purchaseOrder->transaction;
    expect($transaction)->not->toBeNull();
    expect($transaction->staff_id)->toBe($this->assignee->id);
    expect($transaction->staff_id)->not->toBe($this->creator->id);
});
