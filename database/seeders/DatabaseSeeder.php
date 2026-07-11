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
                TagsSeeder::class,
                SublimationSeeder::class,
                // TransactionSeeder::class,
                // PurchaseOrderSeeder::class,
                // ExpenseSeeder::class,
                HolidaySeeder::class,
                SssBracketSeeder::class,
                // AttendanceDemoSeeder::class,
                // EmployeeScenarioSeeder::class,
                // DemoSeeder::class,
            ]);
        } else {
            $this->call([
                BranchSeeder::class,
                UsersSeeder::class,
            ]);
        }
    }
}
