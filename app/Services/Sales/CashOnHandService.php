<?php

namespace App\Services\Sales;

use App\Models\CashOnHand;
use Illuminate\Support\Facades\DB;

class CashOnHandService
{
    /**
     * Adjust the daily cash balance for a specific branch.
     *
     * @param  string  $type  'revenue' or 'expense'
     */
    public function adjustBalance(int $branchId, float $amount, string $type): void
    {
        DB::transaction(function () use ($branchId, $amount, $type) {
            // 1. Find the record for today or create a base one with 0 amount
            $record = CashOnHand::firstOrCreate(
                ['branch_id' => $branchId],
                ['amount' => 0]
            );

            // 2. Perform Atomic math
            if ($type === 'revenue') {
                $record->increment('amount', $amount);
            } else {
                $record->decrement('amount', $amount);
            }
        });
    }
}
