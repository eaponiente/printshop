<?php

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ExpenseSeeder extends Seeder
{
    public function run(): void
    {
        $users = User::query()->whereNot('role', 'superadmin')->pluck('id');
        $branches = Branch::query()->pluck('id');
        // Get valid payment methods from config just like your migration
        $paymentMethods = collect(config()->get('settings.type_of_payment'))->pluck('key');

        // Sample data for variety
        $vendors = ['Amazon Business', 'Staples', 'Local Utility Co', 'Google Cloud', 'Starbucks', 'Shell Gas'];
        $descriptions = ['Monthly hosting fee', 'Office stationery', 'Client lunch', 'Internet bill', 'Fuel for delivery van', 'Software subscription'];

        for ($i = 0; $i < 10; $i++) {
            $amount = rand(1000, 50000) / 100; // Generates amounts between 10.00 and 500.00

            DB::table('expenses')->insert([
                'description' => $descriptions[array_rand($descriptions)],
                'vendor_name' => $vendors[array_rand($vendors)],
                'amount' => $amount,
                'payment_method' => $paymentMethods->random(),

                // Assuming ID 1 exists for user and branch - adjust if necessary
                'user_id' => $users->random(),
                'branch_id' => $branches->random(),

                'status' => ['pending', 'approved', 'rejected', 'reimbursed'][rand(0, 3)],
                'expense_date' => Carbon::now()->subDays(rand(1, 30))->format('Y-m-d'),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }
}
