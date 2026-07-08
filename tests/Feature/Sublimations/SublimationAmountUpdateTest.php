<?php

use App\Enums\Sales\TransactionStatus;
use App\Enums\Sublimations\SublimationStatus;
use App\Models\Branch;
use App\Models\Customer;
use App\Models\Sublimation;
use App\Models\Tag;
use App\Models\Transaction;
use App\Models\User;
use Payroll\Audit\Models\AuditLog;

beforeEach(function () {
    $this->branch = Branch::factory()->create();
    $this->admin = User::factory()->create([
        'role' => 'admin',
        'branch_id' => $this->branch->id,
    ]);
    $this->staff = User::factory()->create([
        'role' => 'staff',
        'branch_id' => $this->branch->id,
    ]);
    $this->customer = Customer::factory()->create();
    $this->tag = Tag::create(['name' => 'Jersey', 'color' => '#000', 'price_per_piece' => 100]);
});

/**
 * Build a sublimation + linked transaction in a given state.
 */
function makeSublimationWithTransaction(array $attrs): Sublimation
{
    $branch = test()->branch;
    $customer = test()->customer;
    $admin = test()->admin;

    $transaction = Transaction::factory()->create([
        'branch_id' => $branch->id,
        'customer_id' => $customer->id,
        'staff_id' => $admin->id,
        'particular' => 'Sublimation',
        'amount_total' => $attrs['tx_total'],
        'amount_paid' => $attrs['tx_paid'],
        'status' => $attrs['tx_status'],
    ]);

    return Sublimation::factory()->create([
        'branch_id' => $branch->id,
        'customer_id' => $customer->id,
        'user_id' => $admin->id,
        'status' => $attrs['status'],
        'amount_total' => $attrs['tx_total'],
        'transaction_id' => $transaction->id,
    ]);
}

/**
 * A valid update payload; override individual keys as needed.
 */
function updatePayload(Sublimation $sub, array $overrides = []): array
{
    return array_merge([
        'description' => $sub->description,
        'branch_id' => $sub->branch_id,
        'customer_id' => $sub->customer_id,
        'amount_total' => $sub->amount_total,
        'transaction_type' => 'retail',
        'production_authorized' => false,
        'tag_ids' => [['id' => test()->tag->id, 'quantity' => 2]],
        'user_id' => $sub->user_id,
    ], $overrides);
}

it('allows raising the amount at downpayment_complete and keeps the transaction partial', function () {
    $sub = makeSublimationWithTransaction([
        'status' => SublimationStatus::DOWNPAYMENT_COMPLETE->value,
        'tx_total' => 1000,
        'tx_paid' => 500,
        'tx_status' => TransactionStatus::PARTIAL->value,
    ]);

    $this->actingAs($this->admin)
        ->from(route('sublimations.index'))
        ->put(route('sublimations.update', $sub->id), updatePayload($sub, ['amount_total' => 1500]))
        ->assertRedirect(route('sublimations.index'))
        ->assertSessionHasNoErrors();

    $sub->refresh();
    $tx = $sub->transaction()->first();

    expect((float) $sub->amount_total)->toBe(1500.0);
    expect((float) $tx->amount_total)->toBe(1500.0);
    expect($tx->status)->toBe(TransactionStatus::PARTIAL->value);
    expect((float) $tx->balance)->toBe(1000.0);
});

it('recomputes the transaction to paid when the new total equals the amount already paid', function () {
    $sub = makeSublimationWithTransaction([
        'status' => SublimationStatus::DOWNPAYMENT_COMPLETE->value,
        'tx_total' => 1000,
        'tx_paid' => 500,
        'tx_status' => TransactionStatus::PARTIAL->value,
    ]);

    $this->actingAs($this->admin)
        ->from(route('sublimations.index'))
        ->put(route('sublimations.update', $sub->id), updatePayload($sub, ['amount_total' => 500]))
        ->assertRedirect(route('sublimations.index'))
        ->assertSessionHasNoErrors();

    $tx = $sub->transaction()->first();

    expect($tx->status)->toBe(TransactionStatus::PAID->value);
    expect($tx->fulfilled_at)->not->toBeNull();
    expect((float) $tx->balance)->toBe(0.0);
});

it('rejects lowering the amount below what has already been paid', function () {
    $sub = makeSublimationWithTransaction([
        'status' => SublimationStatus::DOWNPAYMENT_COMPLETE->value,
        'tx_total' => 1000,
        'tx_paid' => 500,
        'tx_status' => TransactionStatus::PARTIAL->value,
    ]);

    $this->actingAs($this->admin)
        ->from(route('sublimations.index'))
        ->put(route('sublimations.update', $sub->id), updatePayload($sub, ['amount_total' => 400]))
        ->assertRedirect(route('sublimations.index'))
        ->assertSessionHasErrors('message');

    $sub->refresh();
    $tx = $sub->transaction()->first();

    expect((float) $sub->amount_total)->toBe(1000.0);
    expect((float) $tx->amount_total)->toBe(1000.0);
    expect($tx->status)->toBe(TransactionStatus::PARTIAL->value);
});

it('still locks the amount at a later production status', function () {
    $sub = makeSublimationWithTransaction([
        'status' => SublimationStatus::PRINTED->value,
        'tx_total' => 1000,
        'tx_paid' => 500,
        'tx_status' => TransactionStatus::PARTIAL->value,
    ]);

    $this->actingAs($this->admin)
        ->from(route('sublimations.index'))
        ->put(route('sublimations.update', $sub->id), updatePayload($sub, ['amount_total' => 1500]))
        ->assertRedirect(route('sublimations.index'))
        ->assertSessionHasErrors('message');

    $sub->refresh();
    $tx = $sub->transaction()->first();

    expect((float) $sub->amount_total)->toBe(1000.0);
    expect((float) $tx->amount_total)->toBe(1000.0);
});

it('resets the transaction to pending when the downpayment has been refunded to zero', function () {
    // A refunded downpayment leaves amount_paid at 0 while the sublimation is
    // still parked at downpayment_complete.
    $sub = makeSublimationWithTransaction([
        'status' => SublimationStatus::DOWNPAYMENT_COMPLETE->value,
        'tx_total' => 1000,
        'tx_paid' => 0,
        'tx_status' => TransactionStatus::PARTIAL->value,
    ]);

    $this->actingAs($this->admin)
        ->from(route('sublimations.index'))
        ->put(route('sublimations.update', $sub->id), updatePayload($sub, ['amount_total' => 1200]))
        ->assertRedirect(route('sublimations.index'))
        ->assertSessionHasNoErrors();

    $tx = $sub->transaction()->first();

    expect($tx->status)->toBe(TransactionStatus::PENDING->value);
    expect($tx->fulfilled_at)->toBeNull();
    expect((float) $tx->amount_total)->toBe(1200.0);
});

it('flips a fully paid transaction back to partial when the total is raised', function () {
    $sub = makeSublimationWithTransaction([
        'status' => SublimationStatus::DOWNPAYMENT_COMPLETE->value,
        'tx_total' => 1000,
        'tx_paid' => 1000,
        'tx_status' => TransactionStatus::PAID->value,
    ]);
    $sub->transaction()->update(['fulfilled_at' => now()]);

    $this->actingAs($this->admin)
        ->from(route('sublimations.index'))
        ->put(route('sublimations.update', $sub->id), updatePayload($sub, ['amount_total' => 1500]))
        ->assertRedirect(route('sublimations.index'))
        ->assertSessionHasNoErrors();

    $tx = $sub->transaction()->first();

    expect($tx->status)->toBe(TransactionStatus::PARTIAL->value);
    expect($tx->fulfilled_at)->toBeNull();
    expect((float) $tx->balance)->toBe(500.0);
});

it('rejects a lowered total against the freshest amount_paid (no concurrent-payment corruption)', function () {
    $sub = makeSublimationWithTransaction([
        'status' => SublimationStatus::DOWNPAYMENT_COMPLETE->value,
        'tx_total' => 1000,
        'tx_paid' => 500,
        'tx_status' => TransactionStatus::PARTIAL->value,
    ]);

    // Simulate a payment collected after the edit form was opened: the DB now
    // reflects amount_paid = 1000, but a stale client still thinks it is 500.
    Transaction::where('id', $sub->transaction_id)
        ->update(['amount_paid' => 1000, 'status' => TransactionStatus::PAID->value]);

    // 800 looks valid against the stale 500, but must be rejected against the
    // fresh 1000 — otherwise the balance would go negative.
    $this->actingAs($this->admin)
        ->from(route('sublimations.index'))
        ->put(route('sublimations.update', $sub->id), updatePayload($sub, ['amount_total' => 800]))
        ->assertRedirect(route('sublimations.index'))
        ->assertSessionHasErrors('message');

    $tx = $sub->transaction()->first();

    expect((float) $tx->amount_total)->toBe(1000.0);
    expect((float) $tx->balance)->toBe(0.0);
});

it('writes an audit log capturing the amount change and its reason', function () {
    $sub = makeSublimationWithTransaction([
        'status' => SublimationStatus::DOWNPAYMENT_COMPLETE->value,
        'tx_total' => 1000,
        'tx_paid' => 500,
        'tx_status' => TransactionStatus::PARTIAL->value,
    ]);

    $this->actingAs($this->admin)
        ->from(route('sublimations.index'))
        ->put(route('sublimations.update', $sub->id), updatePayload($sub, [
            'amount_total' => 1500,
            'change_reason' => 'customer added items',
        ]))
        ->assertRedirect(route('sublimations.index'))
        ->assertSessionHasNoErrors();

    // Reason is persisted on the transaction ledger row...
    expect($sub->transaction()->first()->change_reason)->toBe('customer added items');

    // ...and an audit entry records the before/after amount plus the reason.
    $log = AuditLog::query()
        ->where('model_type', Sublimation::class)
        ->where('model_id', $sub->id)
        ->where('action', 'amount_updated')
        ->latest('id')
        ->first();

    expect($log)->not->toBeNull();
    expect((float) $log->before['amount_total'])->toBe(1000.0);
    expect((float) $log->after['amount_total'])->toBe(1500.0);
    expect($log->after['change_reason'])->toBe('customer added items');
    expect($log->user_id)->toBe($this->admin->id);
});

it('does not write an audit log when the amount is unchanged', function () {
    $sub = makeSublimationWithTransaction([
        'status' => SublimationStatus::DOWNPAYMENT_COMPLETE->value,
        'tx_total' => 1000,
        'tx_paid' => 500,
        'tx_status' => TransactionStatus::PARTIAL->value,
    ]);

    $this->actingAs($this->admin)
        ->from(route('sublimations.index'))
        ->put(route('sublimations.update', $sub->id), updatePayload($sub, [
            'description' => 'Updated description only',
        ]))
        ->assertRedirect(route('sublimations.index'))
        ->assertSessionHasNoErrors();

    expect(AuditLog::where('action', 'amount_updated')->count())->toBe(0);
});

it('blocks staff from changing the amount once a downpayment has been recorded', function () {
    $sub = makeSublimationWithTransaction([
        'status' => SublimationStatus::DOWNPAYMENT_COMPLETE->value,
        'tx_total' => 1000,
        'tx_paid' => 500,
        'tx_status' => TransactionStatus::PARTIAL->value,
    ]);

    $this->actingAs($this->staff)
        ->from(route('sublimations.index'))
        ->put(route('sublimations.update', $sub->id), updatePayload($sub, ['amount_total' => 1500]))
        ->assertRedirect(route('sublimations.index'))
        ->assertSessionHasErrors('message');

    $sub->refresh();

    expect((float) $sub->amount_total)->toBe(1000.0);
    expect((float) $sub->transaction()->first()->amount_total)->toBe(1000.0);
});

it('allows staff to change the amount while the transaction is still pending', function () {
    $sub = makeSublimationWithTransaction([
        'status' => SublimationStatus::WAITING_FOR_DP->value,
        'tx_total' => 1000,
        'tx_paid' => 0,
        'tx_status' => TransactionStatus::PENDING->value,
    ]);

    $this->actingAs($this->staff)
        ->from(route('sublimations.index'))
        ->put(route('sublimations.update', $sub->id), updatePayload($sub, ['amount_total' => 1200]))
        ->assertRedirect(route('sublimations.index'))
        ->assertSessionHasNoErrors();

    expect((float) $sub->transaction()->first()->amount_total)->toBe(1200.0);
});

it('keeps editing a pre-payment sublimation with a pending transaction in sync', function () {
    $sub = makeSublimationWithTransaction([
        'status' => SublimationStatus::WAITING_FOR_DP->value,
        'tx_total' => 1000,
        'tx_paid' => 0,
        'tx_status' => TransactionStatus::PENDING->value,
    ]);

    $this->actingAs($this->admin)
        ->from(route('sublimations.index'))
        ->put(route('sublimations.update', $sub->id), updatePayload($sub, ['amount_total' => 800]))
        ->assertRedirect(route('sublimations.index'))
        ->assertSessionHasNoErrors();

    $tx = $sub->transaction()->first();

    expect((float) $tx->amount_total)->toBe(800.0);
    expect($tx->status)->toBe(TransactionStatus::PENDING->value);
});
