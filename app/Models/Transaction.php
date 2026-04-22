<?php

namespace App\Models;

use App\Concerns\SaleFilterTrait;
use App\Concerns\Sortable;
use App\Enums\Sales\TransactionStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class Transaction extends Model
{
    use SaleFilterTrait, Sortable, HasFactory;

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
        return DB::transaction(function () {
            $year = now()->year;
            $prefix = "INV-{$year}-";

            // Use lockForUpdate to serialize concurrency
            $lastInvoiceNumber = self::whereYear('created_at', $year)
                ->where('invoice_number', 'like', "{$prefix}%")
                ->lockForUpdate()
                ->latest('id')
                ->value('invoice_number');

            $lastSequence = $lastInvoiceNumber
                ? (int) substr($lastInvoiceNumber, -5) // usually we padding 5 digits
                : 0;

            $sequence = $lastSequence + 1;

            return $prefix . str_pad($sequence, 5, '0', STR_PAD_LEFT);
        });
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
            // Fetch fresh with lock 
            $fresh = self::lockForUpdate()->find($this->id);

            // 1. Calculate new totals
            $newTotalPaid = $fresh->amount_paid + $paymentAmount;

            // 2. Business Rule: Prevent Overpayment
            if ($newTotalPaid > $fresh->amount_total) {
                throw new \Exception("Payment exceeds the remaining balance of {$fresh->balance}");
            }

            // 3. Determine Status and Fulfillment logic
            $status = TransactionStatus::PARTIAL;
            $fulfilledAt = $fresh->fulfilled_at;

            if ($newTotalPaid >= $fresh->amount_total) {
                $status = TransactionStatus::PAID;
                $fulfilledAt = now();
            } elseif ($newTotalPaid <= 0) {
                $status = TransactionStatus::PENDING;
            }

            // 4. Create the split payment record
            $fresh->payments()->create([
                'amount' => $paymentAmount,
                'payment_type' => $paymentType,
                'staff_id' => auth()->id(),
            ]);

            // 5. Update the parent transaction record
            $fresh->update([
                'amount_paid' => $newTotalPaid,
                'status' => $status,
                'fulfilled_at' => $fulfilledAt,
            ]);
        });
    }
}
