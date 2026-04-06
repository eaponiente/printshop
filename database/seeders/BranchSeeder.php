<?php

namespace Database\Seeders;

use App\Models\Branch;
use Illuminate\Database\Seeder;

class BranchSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $branches = [
            ['name' => 'Babak'],
            ['name' => 'Tibungco'],
            ['name' => 'Malita'],
            ['name' => 'Peñaplata'],
        ];

        foreach ($branches as $branch) {
            // This looks for a branch with the same name.
            // If found, it does nothing. If not found, it creates it.
            Branch::firstOrCreate(['name' => $branch['name']]);
        }
    }
}
