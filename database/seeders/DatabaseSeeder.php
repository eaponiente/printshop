<?php

namespace Database\Seeders;

// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        if (! app()->environment('production')) {
            $this->call([
                BranchSeeder::class,
                UsersSeeder::class,
                CustomerSeeder::class,
                HolidaySeeder::class,
                SssBracketSeeder::class,
                // AttendanceDemoSeeder::class,
                // EmployeeScenarioSeeder::class,
                // DemoSeeder::class,
                GarmentTagsSeeder::class,
                SublimationForApprovalSeeder::class,
                AttendanceDemoSeeder::class,
            ]);
        } else {
            $this->call([
                BranchSeeder::class,
                UsersSeeder::class,
            ]);
        }
    }
}
