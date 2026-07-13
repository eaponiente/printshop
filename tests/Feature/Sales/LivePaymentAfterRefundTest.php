<?php

use App\Models\Branch;
use App\Models\CashOnHand;
use App\Models\Transaction;
use App\Models\User;
use App\Services\Sales\SalesService;

/**
 * A refund reverses the full collected amount by appending negative ledger
 * rows and resetting amount_paid. The Partial/Paid payment view must therefore
 * surface only the payments made AFTER the most recent refund — never the
 * positive rows that a later refund already reversed.
 */
function refundScenarioUser(Branch $branch): User
{
    return User::factory()->create([
        'branch_id' => $branch->id,
        'role' => 'superadmin',
    ]);
}

it('lists only the payment made after a refund and excludes reversed ones', function () {
    $branch = Branch::factory()->create();
    $user = refundScenarioUser($branch);
    $this->actingAs($user);

    $transaction = Transaction::factory()->create([
        'amount_total' => 500,
        'amount_paid' => 0,
        'status' => 'pending',
        'branch_id' => $branch->id,
        'staff_id' => $user->id,
    ]);

    // 250 + 250 -> refund -> 500 again
    $transaction->recordPayment(250, 'cash');
    $transaction->recordPayment(250, 'cash');
    $transaction->refundPayment();
    $transaction->recordPayment(500, 'cash');

    $service = app(SalesService::class);
    $filters = ['date' => now()->toDateString(), 'mode' => 'daily', 'status' => 'paid'];

    $rows = $service->getPaymentQuery($filters)->get();

    // Only the live 500 survives; the two reversed 250s are gone.
    expect($rows)->toHaveCount(1)
        ->and((float) $rows->first()->amount)->toEqual(500.0);

    // Summaries clone the same query, so revenue reflects 500 — not 1000.
    $aggregates = $service->getPaymentAggregatesFromPayments($service->getPaymentQuery($filters), $filters);
    $finance = $service->getFinanceSummaryFromPayments($service->getPaymentQuery($filters), $filters);

    expect($aggregates['total_sales'])->toEqual(500.0)
        ->and($aggregates['cash_amount'])->toEqual(500.0)
        ->and($finance['net_income'])->toEqual(500.0);
});

it('excludes a fully-refunded transaction with no re-payment from the payment view', function () {
    $branch = Branch::factory()->create();
    $user = refundScenarioUser($branch);
    $this->actingAs($user);

    $transaction = Transaction::factory()->create([
        'amount_total' => 500,
        'amount_paid' => 0,
        'status' => 'pending',
        'branch_id' => $branch->id,
        'staff_id' => $user->id,
    ]);

    $transaction->recordPayment(500, 'cash');
    $transaction->refundPayment();

    $service = app(SalesService::class);
    $filters = ['date' => now()->toDateString(), 'mode' => 'daily'];

    expect($service->getPaymentQuery($filters)->get())->toHaveCount(0)
        ->and($service->getPaymentAggregatesFromPayments($service->getPaymentQuery($filters), $filters)['total_sales'])
        ->toEqual(0.0);
});

it('stamps refunded_at on reversed rows and leaves the live row null', function () {
    $branch = Branch::factory()->create();
    $user = refundScenarioUser($branch);
    $this->actingAs($user);

    $transaction = Transaction::factory()->create([
        'amount_total' => 500,
        'amount_paid' => 0,
        'status' => 'pending',
        'branch_id' => $branch->id,
        'staff_id' => $user->id,
    ]);

    $transaction->recordPayment(250, 'cash');
    $transaction->recordPayment(250, 'cash');
    $transaction->refundPayment();
    $transaction->recordPayment(500, 'cash');

    $payments = $transaction->fresh()->payments()->get();

    // The two reversed 250s carry refunded_at; the live 500 does not.
    expect($payments->where('amount', 250)->whereNull('refunded_at'))->toHaveCount(0)
        ->and($payments->where('amount', 250)->whereNotNull('refunded_at'))->toHaveCount(2)
        ->and($payments->firstWhere('amount', 500)->refunded_at)->toBeNull();
});

it('classifies live payments by refunded_at, not id ordering', function () {
    $branch = Branch::factory()->create();
    $user = refundScenarioUser($branch);
    $this->actingAs($user);

    $transaction = Transaction::factory()->create([
        'amount_total' => 100,
        'amount_paid' => 0,
        'status' => 'pending',
        'branch_id' => $branch->id,
        'staff_id' => $user->id,
    ]);

    // A negative (refund) row with a HIGHER id than a positive row that is
    // explicitly still live. The old id-heuristic would have excluded the
    // positive; the refunded_at marker must keep it.
    $live = $transaction->payments()->create(['amount' => 100, 'payment_type' => 'cash', 'staff_id' => $user->id]);
    $transaction->payments()->create(['amount' => -40, 'payment_type' => 'cash', 'staff_id' => $user->id]);

    $liveIds = $transaction->payments()->live()->pluck('id');

    expect($liveIds)->toContain($live->id)
        ->and($liveIds)->toHaveCount(1);

    // Stamping it flips the classification regardless of id.
    $live->update(['refunded_at' => now()]);
    expect($transaction->payments()->live()->count())->toBe(0);
});

it('reverses only the currently-held amount on a second refund', function () {
    $branch = Branch::factory()->create();
    CashOnHand::create(['branch_id' => $branch->id, 'amount' => 1000]);
    $user = refundScenarioUser($branch);
    $this->actingAs($user);

    $transaction = Transaction::factory()->create([
        'amount_total' => 500,
        'amount_paid' => 0,
        'status' => 'pending',
        'branch_id' => $branch->id,
        'staff_id' => $user->id,
    ]);

    // First cycle: collect 500, refund it back.
    $transaction->recordPayment(500, 'cash');
    $this->actingAs($user)->patch(route('sales.refund-payment', $transaction))->assertSessionHasNoErrors();

    // Second cycle: collect 500 again, refund it back.
    $transaction->fresh()->recordPayment(500, 'cash');
    $this->actingAs($user)->patch(route('sales.refund-payment', $transaction))->assertSessionHasNoErrors();

    // Each refund reversed exactly 500, so the two negative rows total -1000
    // (NOT -1500, which the old "sum all positives" logic would have produced),
    // and cash-on-hand returns to its starting 1000 - 500 - 500 = 0.
    $negatives = $transaction->fresh()->payments()->where('amount', '<', 0)->get();

    expect((float) $negatives->sum('amount'))->toEqual(-1000.0)
        ->and($transaction->fresh()->amount_paid)->toEqual(0.0)
        ->and((float) CashOnHand::query()->where('branch_id', $branch->id)->value('amount'))->toEqual(0.0);
});
