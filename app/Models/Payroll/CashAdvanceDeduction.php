<?php

namespace App\Models\Payroll;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CashAdvanceDeduction extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
        ];
    }

    public function payrollPeriodItem(): BelongsTo
    {
        return $this->belongsTo(PayrollPeriodItem::class);
    }

    public function cashAdvance(): BelongsTo
    {
        return $this->belongsTo(CashAdvance::class);
    }
}
