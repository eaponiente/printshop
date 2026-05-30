<?php

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\Payroll\Employee;
use App\Models\Payroll\Salary;
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

            $this->ensureEmployee($superadmin, $defaultBranchId, 1000);

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

                $this->ensureEmployee($staff, $obj->id, 510);

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

                $this->ensureEmployee($admin, $obj->id, 610);
            }
        });
    }

    private function ensureEmployee(User $user, int $branchId, float $dailyRate): void
    {
        if ($user->employee_id && Employee::find($user->employee_id)) {
            return;
        }

        $emp = Employee::firstOrCreate(
            ['first_name' => $user->first_name, 'last_name' => $user->last_name, 'branch_id' => $branchId],
            [
                'hire_date' => now()->subYear()->toDateString(),
                'position' => 'regular',
                'status' => 'active',
                'current_daily_rate' => $dailyRate,
            ]
        );

        if (! Salary::where('employee_id', $emp->id)->exists()) {
            Salary::createForEmployee($emp, $dailyRate, now()->subYear()->toDateString(), 'Initial salary');
        }

        $user->update(['employee_id' => $emp->id]);
    }
}
