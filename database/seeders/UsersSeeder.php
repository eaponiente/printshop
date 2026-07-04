<?php

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\Payroll\Employee;
use App\Models\Payroll\Salary;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Payroll\Employee\Enums\EmployeePosition;
use Payroll\Employee\Enums\EmployeeStatus;

class UsersSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $firstBranch = Branch::first();
        $defaultBranchId = $firstBranch?->id ?? 1;

        DB::transaction(function () {
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

                $this->ensureEmployeeRecord($staff, 500);

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

                $this->ensureEmployeeRecord($admin, 700);
            }
        });
    }

    /**
     * Every staff/admin user needs a linked Employee record carrying
     * government benefit ID numbers (and their matching weekly deduction
     * amounts, since payroll requires both to apply a deduction), so payroll
     * and attendance features work out of the box for seeded users.
     */
    private function ensureEmployeeRecord(User $user, float $dailyRate): void
    {
        $govtIds = [
            'sss_number' => fake()->numerify('##-#######-#'),
            'philhealth_number' => fake()->numerify('##-#########-#'),
            'pagibig_number' => fake()->numerify('####-####-####'),
        ];

        $deductions = [
            'sss_deduction_per_week' => 165.75,
            'philhealth_deduction_per_week' => 82.88,
            'pagibig_deduction_per_week' => 50.00,
        ];

        if ($user->employee_id) {
            $user->employee()->update(array_merge($govtIds, $deductions));

            return;
        }

        $employee = Employee::create(array_merge([
            'first_name' => $user->first_name,
            'last_name' => $user->last_name,
            'branch_id' => $user->branch_id,
            'hire_date' => now()->subYear()->toDateString(),
            'position' => EmployeePosition::REGULAR->value,
            'status' => EmployeeStatus::ACTIVE->value,
            'current_daily_rate' => $dailyRate,
        ], $govtIds, $deductions));

        Salary::createForEmployee(
            $employee,
            $dailyRate,
            $employee->hire_date->toDateString(),
            'Initial salary (seeded)'
        );

        $user->update(['employee_id' => $employee->id]);
    }
}
