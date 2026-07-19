<?php

use App\Models\Transaction;
use Carbon\Carbon;

afterEach(function () {
    Carbon::setTestNow();
});

it('increments the sequence within the same year', function () {
    Carbon::setTestNow(Carbon::parse('2026-06-15 10:00:00'));

    Transaction::factory()->create(['invoice_number' => Transaction::generateNumber()]);
    $second = Transaction::generateNumber();

    expect($second)->toBe('INV-2026-00002');
});

it('resets the sequence to 00001 on the first invoice of the new year', function () {
    Carbon::setTestNow(Carbon::parse('2026-12-31 23:59:00'));
    Transaction::factory()->create(['invoice_number' => Transaction::generateNumber()]); // INV-2026-00001

    Carbon::setTestNow(Carbon::parse('2027-01-01 00:01:00'));

    expect(Transaction::generateNumber())->toBe('INV-2027-00001');
});

it('does not re-issue a duplicate when created_at year lags the number year', function () {
    // Regression for the year-rollover collision: a row numbered for the new
    // year whose created_at was persisted in the old calendar year (clock skew
    // across midnight) must not reset the sequence back to 00001.
    Carbon::setTestNow(Carbon::parse('2027-01-01 00:01:00'));

    $first = Transaction::generateNumber();
    expect($first)->toBe('INV-2027-00001');

    // Persist it with a created_at that still resolves to the previous year.
    Transaction::factory()->create([
        'invoice_number' => $first,
        'created_at' => '2026-12-31 23:59:59',
    ]);

    // The next number must advance, not collide with the existing INV-2027-00001.
    expect(Transaction::generateNumber())->toBe('INV-2027-00002');
});

it('ignores soft-deleted rows only by keeping their sequence reserved', function () {
    Carbon::setTestNow(Carbon::parse('2026-06-15 10:00:00'));

    $t = Transaction::factory()->create(['invoice_number' => Transaction::generateNumber()]); // 00001
    $t->delete();

    // withTrashed() means the deleted row still counts, so the next number is 00002.
    expect(Transaction::generateNumber())->toBe('INV-2026-00002');
});
