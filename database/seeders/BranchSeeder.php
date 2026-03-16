<?php

namespace Database\Seeders;

use App\Models\Branch;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class BranchSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $branches = [
            ['name' => 'Main Headquarters'],
            ['name' => 'North Side Branch'],
            ['name' => 'South Station'],
            ['name' => 'East Coast Office'],
            ['name' => 'West End Branch'],
        ];

        foreach ($branches as $branch) {
            // Using Branch::create ensures timestamps are handled automatically
            Branch::create($branch);
        }
    }
}
