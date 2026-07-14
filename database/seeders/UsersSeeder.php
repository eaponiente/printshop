<?php

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\Payroll\Employee;
use App\Models\Payroll\EmployeeSchedule;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class UsersSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $firstBranch = Branch::first();
        $defaultBranchId = $firstBranch?->id ?? 1;

        DB::transaction(function () use ($defaultBranchId) {
            $superadmin = User::updateOrCreate(
                ['username' => 'superadmin'],
                [
                    'first_name' => 'Jacob',
                    'last_name' => 'Elemento',
                    'password' => Hash::make('password'),
                    'role' => 'superadmin',
                    'branch_id' => null,
                ]
            );

            if (app()->environment('production')) {
                return;
            }

            // Superadmin has no branch, so anchor its employee record to the
            // default branch (employees.branch_id is a required FK).
            $this->associateEmployee($superadmin, $defaultBranchId);

            foreach (Branch::all() as $obj) {
                $name = str_replace('ñ', 'n', strtolower($obj->name));

                $staff = User::updateOrCreate(
                    ['username' => "{$name}_staff"],
                    [
                        'first_name' => fake()->firstName,
                        'last_name' => 'Staff',
                        'password' => Hash::make('password'),
                        'role' => 'staff',
                        'branch_id' => $obj->id,
                    ]
                );

                $admin = User::updateOrCreate(
                    ['username' => "{$name}_admin"],
                    [
                        'first_name' => fake()->firstName,
                        'last_name' => 'Admin',
                        'password' => Hash::make('password'),
                        'role' => 'admin',
                        'branch_id' => $obj->id,
                    ]
                );

                $this->associateEmployee($staff, $obj->id);
                $this->associateEmployee($admin, $obj->id);
            }
        });
    }

    /**
     * Create and link an employee record (with a default schedule) for a user.
     * Idempotent: skips users that are already linked so re-seeding is safe.
     */
    private function associateEmployee(User $user, int $branchId): void
    {
        if ($user->employee_id) {
            return;
        }

        $employee = Employee::create([
            'first_name' => $user->first_name,
            'last_name' => $user->last_name,
            'branch_id' => $branchId,
            'hire_date' => now()->toDateString(),
            'position' => 'regular',
            'status' => 'active',
            'current_daily_rate' => 510,
            'default_paid_leave_days' => 5,
            'paid_leave_balance' => 5,
        ]);

        $user->update(['employee_id' => $employee->id]);

        // 8:00 AM start, Monday–Saturday (Sunday off). The 17:30 end with a
        // 30-min unpaid tail is the standard 8-hour paid day.
        EmployeeSchedule::create([
            'employee_id' => $employee->id,
            'start_time' => '08:00',
            'end_time' => '17:30',
            'unpaid_tail_minutes' => 30,
            'rest_days' => [0], // Sunday
            'effective_from' => now()->toDateString(),
            'is_active' => true,
        ]);
    }
}
