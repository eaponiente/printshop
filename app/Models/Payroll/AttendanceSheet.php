<?php

namespace App\Models\Payroll;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AttendanceSheet extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'date' => 'date:Y-m-d',
            'rest_days' => 'array',
            'daily_rate' => 'decimal:2',
            'late_deduction' => 'decimal:2',
            'undertime_deduction' => 'decimal:2',
            'overtime_pay' => 'decimal:2',
            'overtime_multiplier' => 'decimal:2',
            'holiday_pay' => 'decimal:2',
            'leave_hours_credited' => 'decimal:2',
            'fine_deduction' => 'decimal:2',
            'hours_worked' => 'decimal:2',
            'daily_wage' => 'decimal:2',
            'incentive' => 'decimal:2',
            'is_present' => 'boolean',
            'is_rest_day' => 'boolean',
            'leave_is_paid' => 'boolean',
            'locked_at' => 'datetime',
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function scopeForDate($query, string $date)
    {
        return $query->where('date', $date);
    }

    public function scopeForDateRange($query, string $start, string $end)
    {
        return $query->whereBetween('date', [$start, $end]);
    }

    public function scopeUnlocked($query)
    {
        return $query->whereNull('locked_at');
    }

    public function scopeLocked($query)
    {
        return $query->whereNotNull('locked_at');
    }

    public function isLocked(): bool
    {
        return $this->locked_at !== null;
    }
}
