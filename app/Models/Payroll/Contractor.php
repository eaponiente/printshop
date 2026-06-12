<?php

namespace App\Models\Payroll;

use App\Models\Branch;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Contractor extends Model
{
    use SoftDeletes;

    protected $guarded = ['id'];

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function projects(): HasMany
    {
        return $this->hasMany(ContractorProject::class);
    }

    public function cashAdvances(): HasMany
    {
        return $this->hasMany(ContractorCashAdvance::class);
    }

    public function payrollPeriodItems(): HasMany
    {
        return $this->hasMany(PayrollPeriodContractorItem::class);
    }
}
