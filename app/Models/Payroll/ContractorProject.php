<?php

namespace App\Models\Payroll;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ContractorProject extends Model
{
    use SoftDeletes;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'contract_amount' => 'decimal:2',
            'installment_amount' => 'decimal:2',
            'start_date' => 'date:Y-m-d',
            'end_date' => 'date:Y-m-d',
        ];
    }

    public function contractor(): BelongsTo
    {
        return $this->belongsTo(Contractor::class);
    }

    public function payrollItems(): HasMany
    {
        return $this->hasMany(PayrollPeriodContractorItem::class, 'project_id');
    }

    public function isFullyPaid(): bool
    {
        return $this->remaining_installments <= 0;
    }

    public function activate(): void
    {
        $this->update([
            'status' => 'active',
            'remaining_installments' => $this->total_installments,
            'installment_amount' => round($this->contract_amount / $this->total_installments, 2),
        ]);
    }

    public function consumeInstallment(): void
    {
        $this->decrement('remaining_installments');

        if ($this->remaining_installments <= 1) {
            $this->update(['status' => 'completed']);
        }
    }
}
