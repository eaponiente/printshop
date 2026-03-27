<?php

namespace Database\Seeders;

use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            BranchSeeder::class,
            UsersSeeder::class,
            CustomerSeeder::class,
            TransactionSeeder::class,
            TagsSeeder::class,
            SublimationSeeder::class,
            PurchaseOrderSeeder::class,
            ExpenseSeeder::class,
        ]);
    }
}
