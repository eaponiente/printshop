<?php

namespace App\Models;

use App\Concerns\Sortable;
use Illuminate\Database\Eloquent\Model;

class Transaction extends Model
{
    use Sortable;

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
}
