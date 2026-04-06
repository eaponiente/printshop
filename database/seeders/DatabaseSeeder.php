<?php

namespace Database\Seeders;

// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\URL;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        if (app()->environment('production')) {
            $this->call([
                BranchSeeder::class,
                UsersSeeder::class,
            ]);
        } else {
            $this->call([
                BranchSeeder::class,
                UsersSeeder::class,
                CustomerSeeder::class,
                TransactionSeeder::class,
                SublimationSeeder::class,
                PurchaseOrderSeeder::class,
                ExpenseSeeder::class,
            ]);
        }


    }
}
