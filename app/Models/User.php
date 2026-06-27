<?php

namespace App\Models;

use App\Enums\Users\UserRole;
use App\Models\Payroll\Employee;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Payroll\Employee\Enums\EmployeeStatus;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, SoftDeletes, TwoFactorAuthenticatable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'username',
        'first_name',
        'last_name',
        'password',
        'role',
        'branch_id',
        'employee_id',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'two_factor_secret',
        'two_factor_recovery_codes',
        'remember_token',
    ];

    protected $appends = [
        'fullname',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'password' => 'hashed',
        ];
    }

    protected function fullname(): Attribute
    {
        return Attribute::make(
            get: fn () => "{$this->first_name} {$this->last_name}"
        );
    }

    public function isSuperAdmin(): bool
    {
        return $this->role === UserRole::SUPERADMIN->value;
    }

    public function isAdmin(): bool
    {
        return $this->role === UserRole::ADMIN->value;
    }

    public function isStaff(): bool
    {
        return $this->role === UserRole::STAFF->value;
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function canLogin(): bool
    {
        if (! $this->employee_id) {
            return true;
        }

        $employee = $this->employee()->withTrashed()->first();

        if (! $employee) {
            return true;
        }

        if ($employee->trashed()) {
            return false;
        }

        return $employee->status === EmployeeStatus::ACTIVE;
    }

    public function endorsements()
    {
        return $this->hasMany(Endorsement::class);
    }

    public function transactions()
    {
        return $this->hasMany(Transaction::class, 'staff_id');
    }

    public function sublimations()
    {
        return $this->hasMany(Sublimation::class, 'user_id');
    }
}
