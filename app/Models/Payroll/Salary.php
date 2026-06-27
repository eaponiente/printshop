<?php

namespace App\Models\Payroll;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Salary extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'daily_rate' => 'decimal:2',
            'effective_date' => 'date:Y-m-d',
            'end_date' => 'date:Y-m-d',
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public static function createForEmployee(Employee $employee, float $dailyRate, string $effectiveDate, ?string $notes = null): self
    {
        self::where('employee_id', $employee->id)
            ->whereNull('end_date')
            ->update(['end_date' => now()->toDateString()]);

        return self::create([
            'employee_id' => $employee->id,
            'daily_rate' => $dailyRate,
            'effective_date' => $effectiveDate,
            'notes' => $notes,
        ]);
    }
}
