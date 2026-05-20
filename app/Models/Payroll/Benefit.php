<?php

namespace App\Models\Payroll;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Benefit extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'employer_contribution_percent' => 'decimal:2',
            'employee_contribution_percent' => 'decimal:2',
            'employer_contribution_cap' => 'decimal:2',
            'employee_contribution_cap' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    public function employees(): BelongsToMany
    {
        return $this->belongsToMany(Employee::class, 'benefit_employee')
            ->withPivot(['member_number', 'effective_date', 'end_date', 'custom_employer_cap', 'custom_employee_cap', 'is_active'])
            ->withTimestamps();
    }
}
