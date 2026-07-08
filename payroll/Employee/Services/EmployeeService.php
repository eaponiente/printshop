<?php

namespace Payroll\Employee\Services;

use App\Enums\Sales\TransactionStatus;
use App\Enums\Sublimations\SublimationStatus;
use App\Models\Payroll\Employee;
use App\Models\Payroll\EmployeeSchedule;
use App\Models\Payroll\Salary;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class EmployeeService
{
    public function create(array $data): Employee
    {
        $username = $data['username'];
        $password = $data['password'];
        $role = $data['role'];
        unset($data['username'], $data['password'], $data['password_confirmation'], $data['role']);

        $dailyRate = $data['daily_rate'];
        unset($data['daily_rate']);
        $data['current_daily_rate'] = $dailyRate;

        return DB::transaction(function () use ($data, $dailyRate, $username, $password, $role) {
            $employee = Employee::create($data);
            $employee->update([
                'default_paid_leave_days' => $data['default_paid_leave_days'] ?? 5,
                'paid_leave_balance' => $data['paid_leave_balance'] ?? 5,
            ]);

            Salary::createForEmployee(
                $employee,
                $dailyRate,
                $data['hire_date'],
                'Initial salary on hire'
            );

            User::create([
                'first_name' => $employee->first_name,
                'last_name' => $employee->last_name,
                'username' => $username,
                'password' => bcrypt($password),
                'role' => $role,
                'branch_id' => $employee->branch_id,
                'employee_id' => $employee->id,
            ]);

            return $employee;
        });
    }

    public function update(Employee $employee, array $data): void
    {
        $username = $data['username'] ?? null;
        $password = $data['password'] ?? null;
        $role = $data['role'] ?? null;
        unset($data['username'], $data['password'], $data['password_confirmation'], $data['role']);

        $dailyRate = $data['daily_rate'];
        unset($data['daily_rate']);
        $data['current_daily_rate'] = $dailyRate;

        DB::transaction(function () use ($employee, $data, $dailyRate, $username, $password, $role) {
            $salaryChanged = (float) $dailyRate !== (float) $employee->current_daily_rate;

            $employee->update($data);

            if ($salaryChanged) {
                Salary::createForEmployee(
                    $employee,
                    $dailyRate,
                    now()->toDateString(),
                    'Salary adjusted via employee update'
                );
            }

            $user = $employee->user;

            if ($user) {
                $updates = [];
                if ($username !== null) {
                    $updates['username'] = $username;
                }
                if ($role !== null) {
                    $updates['role'] = $role;
                }
                if (! empty($password)) {
                    $updates['password'] = bcrypt($password);
                }
                // Mirror the employee's branch/name onto the linked user whenever
                // they change — independent of any credential edit. A branch-only
                // update must not leave the login account pointing at the old branch.
                if ($employee->wasChanged('branch_id')) {
                    $updates['branch_id'] = $employee->branch_id;
                }
                if ($employee->wasChanged('first_name')) {
                    $updates['first_name'] = $employee->first_name;
                }
                if ($employee->wasChanged('last_name')) {
                    $updates['last_name'] = $employee->last_name;
                }

                if (! empty($updates)) {
                    $user->update($updates);
                }
            } elseif ($username && ! empty($password) && $role) {
                // Legacy employee with no linked user — create one now.
                User::create([
                    'first_name' => $employee->first_name,
                    'last_name' => $employee->last_name,
                    'username' => $username,
                    'password' => bcrypt($password),
                    'role' => $role,
                    'branch_id' => $employee->branch_id,
                    'employee_id' => $employee->id,
                ]);
            }
        });
    }

    public function delete(Employee $employee): void
    {
        DB::transaction(function () use ($employee) {
            if ($user = $employee->user) {
                $user->delete();
            }
            $employee->delete();
        });
    }

    public function deactivate(Employee $employee): void
    {
        $employee->update(['status' => 'inactive']);
    }

    public function hasRelatedRecords(Employee $employee): bool
    {
        $hasPayrollRecords = $employee->timeLogs()->exists()
            || $employee->attendanceSheets()->exists()
            || $employee->payrollPeriodItems()->exists()
            || $employee->leaveRequests()->exists()
            || $employee->overtimeRequests()->exists()
            || $employee->correctionRequests()->exists()
            || $employee->cashAdvances()->exists()
            || $employee->fines()->exists()
            || $employee->salaries()->exists()
            || $employee->schedules()->exists()
            || $employee->benefits()->exists();

        if ($hasPayrollRecords) {
            return true;
        }

        // The linked login user may have active sales/sublimations even though
        // the employee record itself has no payroll history.
        if ($user = $employee->user) {
            if ($user->transactions()->where('status', '!=', TransactionStatus::PAID->value)->exists()
                || $user->sublimations()->where('status', '!=', SublimationStatus::COMPLETED->value)->exists()) {
                return true;
            }
        }

        return false;
    }

    public function rehire(Employee $employee, array $data): void
    {
        $dailyRate = $data['daily_rate'];
        $rehireDate = $data['rehire_date'];

        DB::transaction(function () use ($employee, $data, $dailyRate, $rehireDate) {
            $employee->update([
                'status' => 'active',
                'end_date' => null,
                'position' => $data['position'],
                'current_daily_rate' => $dailyRate,
            ]);

            Salary::createForEmployee(
                $employee,
                $dailyRate,
                $rehireDate,
                $data['notes'] ?? 'Rehired'
            );
        });
    }

    public function link(Employee $employee, int $userId): void
    {
        $user = User::findOrFail($userId);
        $user->update(['employee_id' => $employee->id]);
    }

    public function unlink(Employee $employee): void
    {
        $user = User::where('employee_id', $employee->id)->firstOrFail();
        $user->update(['employee_id' => null]);
    }

    public function syncUser(User $user): Employee
    {
        return DB::transaction(function () use ($user) {
            $employee = Employee::create([
                'first_name' => $user->first_name,
                'last_name' => $user->last_name,
                'branch_id' => $user->branch_id,
                'hire_date' => now()->toDateString(),
                'position' => 'regular',
                'status' => 'active',
                'current_daily_rate' => 0,
                'default_paid_leave_days' => 5,
                'paid_leave_balance' => 5,
                'email' => null,
                'phone' => null,
                'address' => null,
                'birth_date' => null,
                'end_date' => null,
                'sss_number' => null,
                'philhealth_number' => null,
                'pagibig_number' => null,
                'tin_number' => null,
                'notes' => null,
            ]);

            $user->update(['employee_id' => $employee->id]);

            EmployeeSchedule::create([
                'employee_id' => $employee->id,
                'start_time' => '08:00',
                'end_time' => '17:30',
                'unpaid_tail_minutes' => 30,
                'rest_days' => [0],
                'effective_from' => now()->toDateString(),
                'is_active' => true,
            ]);

            return $employee;
        });
    }

    public function syncAll(): int
    {
        $users = User::whereNull('employee_id')
            ->whereIn('role', ['admin', 'staff'])
            ->get();

        if ($users->isEmpty()) {
            return 0;
        }

        $created = 0;

        DB::transaction(function () use ($users, &$created) {
            foreach ($users as $user) {
                $employee = Employee::create([
                    'first_name' => $user->first_name,
                    'last_name' => $user->last_name,
                    'branch_id' => $user->branch_id,
                    'hire_date' => now()->toDateString(),
                    'position' => 'regular',
                    'status' => 'active',
                    'current_daily_rate' => 510,
                    'default_paid_leave_days' => 5,
                    'paid_leave_balance' => 5,
                    'email' => null,
                    'phone' => null,
                    'address' => null,
                    'birth_date' => null,
                    'end_date' => null,
                    'sss_number' => null,
                    'philhealth_number' => null,
                    'pagibig_number' => null,
                    'tin_number' => null,
                    'notes' => null,
                ]);

                $user->update(['employee_id' => $employee->id]);

                EmployeeSchedule::create([
                    'employee_id' => $employee->id,
                    'start_time' => '08:00',
                    'end_time' => '17:30',
                    'unpaid_tail_minutes' => 30,
                    'rest_days' => [0],
                    'effective_from' => now()->toDateString(),
                    'is_active' => true,
                ]);

                $created++;
            }
        });

        return $created;
    }

    public function computeActiveSchedule(Employee $employee, string $today): ?EmployeeSchedule
    {

        return $employee->schedules
            ->filter(fn ($s) => $s->is_active
                && $s->effective_from <= $today
                && ($s->effective_to === null || $s->effective_to >= $today))
            ->sortByDesc('effective_from')
            ->first();
    }

    public function buildIndexQuery(array $filters = [], ?string $branchId = null)
    {
        $query = Employee::query()->with('branch', 'user', 'schedules');

        if ($branchId) {
            $query->where('branch_id', $branchId);
        }

        $filterable = [
            'employee_number',
            'first_name',
            'last_name',
            'position',
            'status',
        ];

        foreach ($filters as $filter) {
            $column = $filter['column'] ?? null;
            $value = $filter['value'] ?? null;

            if (! $column || ! $value || ! in_array($column, $filterable, true)) {
                continue;
            }

            if (in_array($column, ['position', 'status'], true)) {
                $query->where($column, $value);
            } else {
                $query->where($column, 'like', "%{$value}%");
            }
        }

        return $query->whereDoesntHave('user', fn ($q) => $q->where('role', 'superadmin'))
            ->orderBy('last_name');
    }

    public function getUnlinkedUsers(bool $isSuperAdmin, ?string $branchId = null)
    {
        $query = User::whereNull('employee_id')
            ->whereIn('role', ['admin', 'staff']);

        if (! $isSuperAdmin && $branchId) {
            $query->where('branch_id', $branchId);
        }

        return $query->get([
            'id',
            'first_name',
            'last_name',
            'username',
            'branch_id',
        ]);
    }
}
