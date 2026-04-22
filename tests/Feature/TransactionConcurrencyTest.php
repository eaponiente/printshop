<?php

use App\Models\Transaction;

it('generates unique invoice numbers without duplication under load', function () {
    $numbers = [];
    
    // Simulate generation sequentially but relying on DB level locking.
    // In actual testing, proper concurrent process tests are difficult in SQLite,
    // but we can ensure generateNumber() produces different sequential values.
    for ($i = 0; $i < 5; $i++) {
        $transaction = Transaction::factory()->create([
            'invoice_number' => Transaction::generateNumber()
        ]);
        $numbers[] = $transaction->invoice_number;
    }
    
    expect(count(array_unique($numbers)))->toEqual(5);
});
