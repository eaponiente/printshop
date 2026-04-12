<?php

namespace App\Models;

use App\Concerns\SaleFilterTrait;
use App\Concerns\Sortable;
use App\Enums\Sales\TransactionStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class Transaction extends Model
{
    use SaleFilterTrait, Sortable;

    public $guarded = ['id'];

    public function user()
    {
        return $this->belongsTo(User::class, 'staff_id');
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class, 'customer_id');
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class, 'branch_id');
    }

    public function payments()
    {
        return $this->hasMany(Payment::class);
    }

    public function sublimation()
    {
        return $this->hasOne(Sublimation::class);
    }

    /**
     * Generate a unique invoice number.
     * Format: INV-2026-0001
     */
    public static function generateNumber(): string
    {
        $year = now()->year;
        $prefix = "INV-{$year}-";

        // 1. Use a more robust way to find the max sequence in one query
        $lastInvoiceNumber = self::whereYear('created_at', $year)
            ->where('invoice_number', 'like', "{$prefix}%")
            ->latest('id')
            ->value('invoice_number');

        // 2. Extract sequence using a null-safe approach
        $lastSequence = $lastInvoiceNumber
            ? (int) substr($lastInvoiceNumber, -4)
            : 0;

        $sequence = $lastSequence + 1;

        // 3. Loop instead of recursion to prevent Stack Overflow on high collision
        while (true) {
            $candidate = $prefix.str_pad($sequence, 5, '0', STR_PAD_LEFT);

            if (! self::where('invoice_number', $candidate)->exists()) {
                return $candidate;
            }

            $sequence++;
        }
    }

    /**
     * Update the payment progress and auto-transition status.
     *
     * * @param float $paymentAmount The incremental amount being paid now
     * @param  string|null  $reason  Optional audit trail note
     *
     * @throws \Exception
     */
    public function recordPayment(float $paymentAmount, string $paymentType): void
    {
        DB::transaction(function () use ($paymentAmount, $paymentType) {
            // 1. Calculate new totals
            $newTotalPaid = $this->amount_paid + $paymentAmount;

            // 2. Business Rule: Prevent Overpayment
            if ($newTotalPaid > $this->amount_total) {
                throw new \Exception("Payment exceeds the remaining balance of {$this->balance}");
            }

            // 3. Determine Status and Fulfillment logic
            $status = TransactionStatus::PARTIAL;
            $fulfilledAt = $this->fulfilled_at;

            if ($newTotalPaid >= $this->amount_total) {
                $status = TransactionStatus::PAID;
                $fulfilledAt = now();
            } elseif ($newTotalPaid <= 0) {
                $status = TransactionStatus::PENDING;
            }

            // 4. Create the split payment record
            $this->payments()->create([
                'amount' => $paymentAmount,
                'payment_type' => $paymentType,
                'staff_id' => auth()->id(),
            ]);

            // 5. Update the parent transaction record
            $this->update([
                'amount_paid' => $newTotalPaid,
                'status' => $status,
                'fulfilled_at' => $fulfilledAt,
                'change_reason' => $reason ?? $this->change_reason,
            ]);
        });
    }
}
