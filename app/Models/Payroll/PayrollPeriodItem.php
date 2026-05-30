<?php

namespace App\Models\Payroll;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PayrollPeriodItem extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'late_deduction' => 'decimal:2',
            'undertime_deduction' => 'decimal:2',
            'overtime_pay' => 'decimal:2',
            'holiday_pay' => 'decimal:2',
            'fine_deduction' => 'decimal:2',
            'gross_pay' => 'decimal:2',
            'deminimis_earnings' => 'decimal:2',
            'sss_deduction' => 'decimal:2',
            'philhealth_deduction' => 'decimal:2',
            'pagibig_deduction' => 'decimal:2',
            'ca_deduction' => 'decimal:2',
            'net_pay' => 'decimal:2',
            'daily_rate' => 'decimal:2',
        ];
    }

    public function payrollPeriod(): BelongsTo
    {
        return $this->belongsTo(PayrollPeriod::class);
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}
