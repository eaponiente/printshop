<?php

namespace App\Models\Payroll;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PayrollPeriodContractorItem extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'contract_amount' => 'decimal:2',
            'ca_deduction' => 'decimal:2',
            'net_pay' => 'decimal:2',
        ];
    }

    public function payrollPeriod(): BelongsTo
    {
        return $this->belongsTo(PayrollPeriod::class);
    }

    public function contractor(): BelongsTo
    {
        return $this->belongsTo(Contractor::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(ContractorProject::class, 'project_id');
    }
}
