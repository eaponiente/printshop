<?php

namespace Database\Seeders;

use App\Enums\Shared\TypeOfPaymentEnum;
use App\Models\Branch;
use App\Models\Expense;
use App\Models\User;
use App\Services\Sales\CashOnHandService;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ExpenseSeeder extends Seeder
{
    public function run(): void
    {
        $users = User::query()->whereIn('role', ['admin', 'staff'])->get();

        // Get valid payment methods from config just like your migration
        $paymentMethods = collect(TypeOfPaymentEnum::cases());

        // Sample data for variety
        $vendors = ['Amazon Business', 'Staples', 'Local Utility Co', 'Google Cloud', 'Starbucks', 'Shell Gas'];
        $descriptions = ['Monthly hosting fee', 'Office stationery', 'Client lunch', 'Internet bill', 'Fuel for delivery van', 'Software subscription'];

        for ($i = 0; $i < 100; $i++) {
            $amount = rand(300, 5000); // Generates amounts between 10.00 and 500.00

            $user = $users->random();

            $date = Carbon::now()->addDays(random_int(-7, 7))->format('Y-m-d');
            
            $isVoid = rand(1, 100) <= 10; // 10% chance to be voided
            $status = $isVoid ? \App\Enums\Expenses\ExpenseStatus::VOID : \App\Enums\Expenses\ExpenseStatus::PAID;

            $expense = Expense::create([
                'description' => $descriptions[array_rand($descriptions)],
                'vendor_name' => rand(0, 1) ? $vendors[array_rand($vendors)] : null,
                'amount' => $amount,
                'status' => $status,
                'payment_type' => rand(0, 1) ? $paymentMethods->random() : null,

                // Assuming ID 1 exists for user and branch - adjust if necessary
                'user_id' => $user->id,
                'branch_id' => $user->branch_id,
                
                'void_reason' => $isVoid ? 'Entered by mistake' : null,

                'expense_date' => $date,
                'created_at' => $date,
                'updated_at' => $date,
            ]);

            if ($expense->status === \App\Enums\Expenses\ExpenseStatus::PAID && $expense->payment_type === TypeOfPaymentEnum::CASH->value) {
                app(CashOnHandService::class)->adjustBalance(
                    $expense->branch_id,
                    $expense->amount,
                    'expense'
                );
            }
        }
    }
}
