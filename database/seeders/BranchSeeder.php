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
            // Using Branch::create ensures timestamps are handled automatically
            Branch::create($branch);
        }
    }
}
