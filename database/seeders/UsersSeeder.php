<?php

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\User;
use Faker\Factory as Faker;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UsersSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $faker = Faker::create();
        $branches = Branch::pluck('id');

        if ($branches->isEmpty()) {
            $this->command->warn('No branches found. Please seed branches before users.');

            return;
        }

        // 1. Create a Fixed Super Admin (For easy login during dev)
        User::create([
            'first_name' => 'Alex',
            'last_name' => 'System',
            'username' => 'superadmin',
            'password' => Hash::make('password'), // Always use a secure hash
            'role' => 'superadmin',
            'branch_id' => $branches->random(),
        ]);

        // 2. Create Realistic Admins and Staff
        $roles = ['admin', 'staff'];

        foreach (range(1, 5) as $index) {
            $firstName = $faker->firstName;
            $lastName = $faker->lastName;

            User::create([
                'first_name' => $firstName,
                'last_name' => $lastName,
                // Generates a clean username like "jdoe" or "s.smith"
                'username' => strtolower($firstName[0].$lastName.$faker->numberBetween(10, 99)),
                'password' => Hash::make('password'),
                'role' => $faker->randomElement($roles),
                'branch_id' => $branches->random(),
                'created_at' => $faker->dateTimeBetween('-6 months', 'now'), // Good for testing your Monthly/Weekly filters!
            ]);
        }
    }
}
