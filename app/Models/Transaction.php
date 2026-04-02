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

    protected $casts = [
        'transaction_date' => 'datetime:Y-m-d h:i A',
    ];

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

    /**
     * Generate a unique invoice number.
     * Format: INV-2026-0001
     */
    public static function generateNumber(): string
    {
        $year = now()->year;

        // Get the last invoice number from this year
        $lastInvoice = self::whereYear('created_at', $year)
            ->orderBy('id', 'desc')
            ->first();

        $sequence = $lastInvoice ? ((int) substr($lastInvoice->invoice_number, -4)) + 1 : 1;

        return 'INV-'.$year.'-'.str_pad($sequence, 4, '0', STR_PAD_LEFT);
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
