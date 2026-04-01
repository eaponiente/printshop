<?php

namespace App\Services\Sales;

use App\Models\CashOnHand;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class CashOnHandService
{
    /**
     * Adjust the daily cash balance for a specific branch.
     *
     * @param  string  $type  'revenue' or 'expense'
     */
    public function adjustBalance(int $branchId, float $amount, string $type, ?string $remarks = null): void
    {
        $date = Carbon::now()->toDateString();
        $userId = auth()->id();

        DB::transaction(function () use ($branchId, $date, $amount, $type, $remarks, $userId) {
            // 1. Find the record for today or create a base one with 0 amount
            $record = CashOnHand::firstOrCreate(
                ['branch_id' => $branchId, 'date' => $date],
                ['amount' => 0, 'user_id' => $userId, 'remarks' => 'Daily initialization']
            );

            // 2. Perform Atomic math
            if ($type === 'revenue') {
                $record->increment('amount', $amount);
            } else {
                $record->decrement('amount', $amount);
            }

            // 3. Update metadata (Who was the last person to touch this today)
            $record->update([
                'user_id' => $userId,
                'remarks' => $remarks ?? $record->remarks,
            ]);
        });
    }
}
