<?php

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UsersSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // 2. Create 1 Super Admin
        User::updateOrCreate(
            ['username' => 'superadmin'],
            [
                'first_name' => 'Jacob',
                'last_name' => 'Elemento',
                'password' => Hash::make('password'),
                'role' => 'superadmin',
                'branch_id' => null, // Super admins usually aren't tied to a branch
            ]
        );

        // 3. Create 4 Staff and 4 Admins (1 for each branch)
        foreach (['babak', 'penaplata', 'malita', 'tibungco'] as $key => $name) {

            // Create Staff for this branch
            User::updateOrCreate(
                ['username' => "{$name}_staff"],
                [
                    'first_name' => fake()->firstName,
                    'last_name' => 'Staff',
                    'password' => Hash::make('password'),
                    'role' => 'staff',
                    'branch_id' => $key + 1,
                ]
            );

            // Create Admin for this branch
            User::updateOrCreate(
                ['username' => "{$name}_admin"],
                [
                    'first_name' => fake()->firstName,
                    'last_name' => 'Admin',
                    'password' => Hash::make('password'),
                    'role' => 'admin',
                    'branch_id' => $key + 1,
                ]
            );
        }
    }
}
