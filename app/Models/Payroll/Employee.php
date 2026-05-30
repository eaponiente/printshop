<?php

namespace App\Models\Payroll;

use App\Models\Branch;
use App\Models\User;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Payroll\Employee\Enums\EmployeePosition;
use Payroll\Employee\Enums\EmployeeStatus;

class Employee extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'hire_date' => 'date:Y-m-d',
            'end_date' => 'date:Y-m-d',
            'birth_date' => 'date:Y-m-d',
            'status' => EmployeeStatus::class,
            'position' => EmployeePosition::class,
            'current_daily_rate' => 'decimal:2',
        ];
    }

    protected $appends = ['full_name'];

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function salaries(): HasMany
    {
        return $this->hasMany(Salary::class)->orderBy('effective_date', 'desc');
    }

    public function currentSalary(): HasMany
    {
        return $this->hasMany(Salary::class)->whereNull('end_date');
    }

    public function benefits(): BelongsToMany
    {
        return $this->belongsToMany(Benefit::class, 'benefit_employee')
            ->withPivot(['member_number', 'effective_date', 'end_date', 'custom_employer_cap', 'custom_employee_cap', 'is_active'])
            ->withTimestamps();
    }

    public function projects(): BelongsToMany
    {
        return $this->belongsToMany(Project::class, 'employee_project')
            ->withPivot(['assigned_at', 'ended_at'])
            ->withTimestamps();
    }

    public function user(): HasOne
    {
        return $this->hasOne(User::class);
    }

    public function schedules(): HasMany
    {
        return $this->hasMany(EmployeeSchedule::class)->orderBy('effective_from', 'desc');
    }

    public function activeSchedule(): ?EmployeeSchedule
    {
        return EmployeeSchedule::activeForDate($this->id, now()->toDateString());
    }

    protected function fullName(): Attribute
    {
        return Attribute::make(
            get: fn () => trim("{$this->first_name} {$this->last_name}"),
        );
    }

    protected static function booted(): void
    {
        static::creating(function (Employee $employee) {
            if (empty($employee->employee_number)) {
                $employee->employee_number = self::generateEmployeeNumber();
            }
        });
    }

    public static function generateEmployeeNumber(): string
    {
        $year = now()->format('Y');
        $latest = self::withTrashed()
            ->where('employee_number', 'like', "EMP-{$year}-%")
            ->latest('id')
            ->first();

        $sequence = $latest ? (int) substr($latest->employee_number, -4) + 1 : 1;

        return sprintf('EMP-%s-%04d', $year, $sequence);
    }
}
