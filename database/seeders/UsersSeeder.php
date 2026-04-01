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
        $branches = Branch::pluck('id');

        if ($branches->isEmpty()) {
            $this->command->warn('No branches found. Please seed branches before users.');

            return;
        }

        // 1. Fixed Super Admin (The "Speaker" of the System)
        User::create([
            'first_name' => 'Jacob',
            'last_name' => 'Elemento',
            'username' => 'superadmin',
            'password' => Hash::make('password'),
            'role' => 'superadmin',
        ]);

        // 1. Fixed Super Admin (The "Speaker" of the System)
        User::create([
            'first_name' => 'Edgar',
            'last_name' => 'Poniente',
            'username' => 'staff',
            'password' => Hash::make('password'),
            'role' => 'staff',
            'branch_id' => $branches->random(),
        ]);

        User::create([
            'first_name' => 'Stone Cold',
            'last_name' => 'Steve Austin',
            'username' => 'admin',
            'password' => Hash::make('password'),
            'role' => 'admin',
            'branch_id' => $branches->random(),
        ]);

        // 2. The Prosecution Panel & Key Complainants
        // Data based on the House Prosecution Team and lead signatories
        $congressionalLeaders = [
            ['first' => 'Zaldy', 'last' => 'Co', 'role' => 'staff'], // 1-Rider (Prosecutor)
            ['first' => 'Gerville', 'last' => 'Luistro', 'role' => 'admin'], // Batangas (Prosecutor)
            ['first' => 'Joel', 'last' => 'Chua', 'role' => 'admin'],       // Manila (Good Gov Chair)
            ['first' => 'Jude', 'last' => 'Acidre', 'role' => 'staff'], // Ako Bicol (Prosecutor)
            ['first' => 'Zia Alonto', 'last' => 'Adiong', 'role' => 'staff'], // Lanao del Sur
            ['first' => 'Terry', 'last' => 'Ridon', 'role' => 'admin'],
        ];

        foreach ($congressionalLeaders as $leader) {
            $firstName = $leader['first'];
            $lastName = $leader['last'];

            // Clean username generation: "m.libanan"
            $username = strtolower(substr($firstName, 0, 1).'.'.str_replace(' ', '', $lastName));

            User::create([
                'first_name' => $firstName,
                'last_name' => $lastName,
                'username' => $username,
                'password' => Hash::make('password'),
                'role' => $leader['role'],
                'branch_id' => $branches->random(),
                'created_at' => now()->subMonths(rand(1, 6)),
            ]);
        }
    }
}
