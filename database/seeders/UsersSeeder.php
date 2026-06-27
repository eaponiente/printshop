<?php

namespace Database\Seeders;

use App\Models\Branch;
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
            }
        });
    }
}
