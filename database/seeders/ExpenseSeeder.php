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
        $users = User::query()->whereNot('role', 'superadmin')->get();

        // Get valid payment methods from config just like your migration
        $paymentMethods = collect(config()->get('settings.type_of_payment'))->pluck('key');

        // Sample data for variety
        $vendors = ['Amazon Business', 'Staples', 'Local Utility Co', 'Google Cloud', 'Starbucks', 'Shell Gas'];
        $descriptions = ['Monthly hosting fee', 'Office stationery', 'Client lunch', 'Internet bill', 'Fuel for delivery van', 'Software subscription'];

        for ($i = 0; $i < 10; $i++) {
            $amount = rand(1000, 50000) / 100; // Generates amounts between 10.00 and 500.00

            $user = $users->random();

            DB::table('expenses')->insert([
                'description' => $descriptions[array_rand($descriptions)],
                'vendor_name' => $vendors[array_rand($vendors)],
                'amount' => $amount,
                'payment_method' => $paymentMethods->random(),

                // Assuming ID 1 exists for user and branch - adjust if necessary
                'user_id' => $user->id,
                'branch_id' => $user->branch_id,

                'expense_date' => Carbon::now()->subDays(rand(1, 30))->format('Y-m-d'),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }
}
