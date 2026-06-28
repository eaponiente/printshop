<?php

namespace App\Models\Payroll;

use Illuminate\Database\Eloquent\Model;

class SssContributionBracket extends Model
{
    protected $table = 'sss_contribution_brackets';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'salary_min' => 'decimal:2',
            'salary_max' => 'decimal:2',
            'employee_percentage' => 'decimal:2',
            'employer_percentage' => 'decimal:2',
            'employee_contribution' => 'decimal:2',
            'employer_contribution' => 'decimal:2',
            'effective_from' => 'date:Y-m-d',
        ];
    }

    public static function findBracket(float $monthlySalary): ?self
    {
        return static::where('salary_min', '<=', $monthlySalary)
            ->where(function ($q) use ($monthlySalary) {
                $q->whereNull('salary_max')->orWhere('salary_max', '>=', $monthlySalary);
            })
            ->orderBy('salary_min')
            ->first();
    }
}
