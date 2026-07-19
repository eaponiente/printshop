<?php

namespace App\Models;

use App\Concerns\SaleFilterTrait;
use App\Concerns\Sortable;
use App\Enums\Sales\TransactionStatus;
use App\Enums\Users\UserRole;
use App\Services\Files\FileUploadService;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;

class Transaction extends Model
{
    use HasFactory, SaleFilterTrait, SoftDeletes, Sortable;

    public $guarded = ['id'];

    protected $hidden = [
        'attachment_path',
    ];

    protected $appends = [
        'attachment_url',
    ];

    public function getAttachmentUrlAttribute(): ?string
    {
        if (! $this->attachment_path) {
            return null;
        }

        if (auth()->user()->role === UserRole::STAFF->value) {
            return null;
        }

        $service = app(FileUploadService::class);

        return $service->getSignedUrl($this->attachment_path);
    }

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
        return $this->hasOne(Sublimation::class, 'transaction_id');
    }

    /**
     * Generate a unique invoice number.
     * Format: INV-2026-00001 (year prefix + 5-digit, per-year sequence).
     */
    public static function generateNumber(): string
    {
        return DB::transaction(function () {
            $year = now()->year;
            $prefix = "INV-{$year}-";

            // Scope the sequence purely by the year embedded in the number, not by
            // created_at. A row's created_at can land in a different calendar year
            // than its number (clock skew across midnight, backdated/imported rows,
            // a future tz change); filtering on whereYear() would then exclude the
            // true latest row, reset the sequence to 1, and re-issue a duplicate
            // that violates the unique invoice_number constraint. lockForUpdate
            // serializes concurrent generation.
            $lastInvoiceNumber = self::where('invoice_number', 'like', "{$prefix}%")
                ->withTrashed()
                ->lockForUpdate()
                ->latest('id')
                ->value('invoice_number');

            $lastSequence = $lastInvoiceNumber
                ? (int) substr($lastInvoiceNumber, -5) // 5-digit zero-padded sequence
                : 0;

            $sequence = $lastSequence + 1;

            return $prefix.str_pad($sequence, 5, '0', STR_PAD_LEFT);
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

    /**
     * Reverse the full collected amount on a partial or paid transaction.
     *
     * We keep payment rows as an immutable ledger and append negative
     * refund entries (one per original payment type) instead of deleting
     * historical collections.
     *
     * @throws \Exception
     */
    public function refundPayment(): float
    {
        return DB::transaction(function () {
            $fresh = self::lockForUpdate()->find($this->id);

            if (! in_array($fresh->status, [TransactionStatus::PARTIAL->value, TransactionStatus::PAID->value])) {
                throw new \Exception('Only partial/paid transactions can be refunded.');
            }

            $positivePayments = $fresh->payments()->live()->get();

            if ($positivePayments->isEmpty()) {
                throw new \Exception('There is no collected amount available to refund.');
            }

            $totalsByType = $positivePayments
                ->groupBy('payment_type')
                ->map(fn ($group) => $group->sum('amount'));

            foreach ($totalsByType as $type => $amount) {
                $fresh->payments()->create([
                    'amount' => -((float) $amount),
                    'payment_type' => $type,
                    'staff_id' => auth()->id(),
                ]);
            }

            // Mark the reversed rows explicitly so scopeLive can exclude them
            // without inferring anything from id ordering.
            $fresh->payments()->whereKey($positivePayments->pluck('id'))->update(['refunded_at' => now()]);

            $fresh->update([
                'amount_paid' => 0,
                'status' => TransactionStatus::PENDING,
                'fulfilled_at' => null,
            ]);

            return (float) $totalsByType->sum();
        });
    }

    public function scopeBranchFilters($query, array $filters)
    {
        $user = auth()->user();
        $filterId = $filters['branch_id'] ?? null;

        // 1. Superadmin Logic: See everything, but allow filtering by branch
        if ($user->isSuperAdmin()) {
            if ($filterId && $filterId !== 'all') {
                $query->where('branch_id', $filterId);
            }
        }
        // 2. Admin Logic: Restrict to their own branch
        elseif ($user->isAdmin()) {
            $query->where('branch_id', $user->branch_id);
        }
        // 3. Staff Logic: Restrict to their own records
        elseif ($user->isStaff()) {
            $query->where('staff_id', $user->id);
        }
    }
}
