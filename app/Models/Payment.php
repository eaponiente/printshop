<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Payment extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    protected $casts = [
        'amount' => 'decimal:2',
        'refunded_at' => 'datetime',
    ];

    public function transaction()
    {
        return $this->belongsTo(Transaction::class);
    }

    public function staff()
    {
        return $this->belongsTo(User::class, 'staff_id');
    }

    /**
     * Positive payments that have NOT been reversed by a refund.
     *
     * Reversal is recorded explicitly: Transaction::refundPayment() stamps
     * refunded_at on every payment it reverses, so a "live" payment is simply
     * a positive row with a null refunded_at. This is an indexed column check
     * (no correlated subquery) and does not depend on id ordering, so it stays
     * correct under out-of-order imports and future partial refunds.
     */
    public function scopeLive($query)
    {
        $query->where('payments.amount', '>', 0)
            ->whereNull('payments.refunded_at');
    }
}
