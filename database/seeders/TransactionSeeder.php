<?php

namespace Database\Seeders;

use App\Enums\Sales\TransactionTypeOfPaymentEnum;
use App\Enums\Sublimations\SublimationStatus;
use App\Models\Customer;
use App\Models\Sublimation;
use App\Models\Transaction;
use App\Models\User;
use App\Services\Sales\CashOnHandService;
use App\Services\Sales\SalesService;
use Carbon\Carbon;
use Faker\Factory;
use Illuminate\Database\Seeder;

class TransactionSeeder extends Seeder
{
    public function run(): void
    {
        $faker = Factory::create();
        $customerIds = Customer::pluck('id');
        $staffMembers = User::whereIn('role', ['staff', 'admin'])->get();

        $standardServices = [
            ['name' => 'Room Accommodation', 'desc' => 'Deluxe Suite - 2 Nights Stay'],
            ['name' => 'Event Hall Rental', 'desc' => 'Function Room B - Half Day Seminar'],
            ['name' => 'Spa Treatment', 'desc' => 'Aromatherapy Massage (90 mins)'],
            ['name' => 'Restaurant Bill', 'desc' => 'Dinner Service - Table 12'],
        ];

        $sublimationProducts = [
            'Full Sublimation Jersey' => ['Basketball set', 'Volleyball uniform'],
            'Corporate Lanyard' => ['1-inch nylon', 'Digital print'],
            'Custom Hoodie' => ['Pullover with back print'],
        ];

        $paymentMethods = TransactionTypeOfPaymentEnum::cases();

        $currentDate = Carbon::now()->subDays(30);
        $iterations = range(1, 450);

        foreach ($iterations as $i) {
            $staff = $staffMembers->random();
            auth()->login($staff);

            $currentDate->addHours(rand(0, 4))->addMinutes(rand(0, 59));
            $date = $currentDate->clone();

            if ($faker->boolean(10)) {
                $this->seedSublimation($faker, $customerIds, $staff, $sublimationProducts, $date);
            } else {
                $this->seedStandardTransaction($faker, $customerIds, $staff, $standardServices, $date, $paymentMethods);
            }
        }
    }

    /**
     * Seed a standard transaction with one or more partial payments spread across days,
     * matching the controller flow: create pending → recordPayment → adjust cash.
     */
    private function seedStandardTransaction($faker, $customerIds, $staff, $services, $date, $paymentMethods)
    {
        $service = $faker->randomElement($services);
        $amountTotal = $service['name'] === 'Event Hall Rental' ? rand(2000, 8000) : rand(50, 500);

        // CREATE — matches SaleController@store: creates pending with amount_paid=0
        $transaction = Transaction::create([
            'invoice_number' => Transaction::generateNumber(),
            'customer_id' => $customerIds->random(),
            'particular' => $service['name'],
            'description' => rand(0, 1) ? $service['desc'] : null,
            'amount_total' => $amountTotal,
            'amount_paid' => 0,
            'status' => 'pending',
            'staff_id' => $staff->id,
            'branch_id' => $staff->branch_id,
            'transaction_date' => $date,
            'created_at' => $date,
            'updated_at' => $date,
        ]);

        // Decide how many payments this transaction gets (0-4 payments)
        $roll = rand(1, 100);

        if ($roll <= 15) {
            // No payment: stays pending
            return;
        }

        if ($roll <= 60) {
            // Full payment in one go
            $this->recordPayment($transaction, $amountTotal, $paymentMethods);
            return;
        }

        // Partial: 2-4 payments spread across consecutive days
        $numPayments = rand(2, 4);
        $remaining = $amountTotal;
        $paymentDate = $date->clone();

        for ($p = 0; $p < $numPayments; $p++) {
            // Guard: nothing left to pay
            if ($remaining <= 0) break;

            $isLast = $p === $numPayments - 1;

            if ($isLast) {
                $payAmount = $remaining;
            } else {
                $chunk = round($remaining * (rand(20, 60) / 100), 2);
                $payAmount = max(0.01, min($chunk, $remaining - 0.01));
            }

            // Final clamp: never exceed remaining balance
            $payAmount = round(min($payAmount, $remaining), 2);

            if ($payAmount <= 0) break;

            $paymentDate->addDays(rand(0, 2));
            auth()->login($staff);

            $this->recordPayment($transaction, $payAmount, $paymentMethods);

            $remaining = round($remaining - $payAmount, 2);
        }
    }

    private function recordPayment(Transaction $transaction, float $amount, array $paymentMethods): void
    {
        $paymentType = collect($paymentMethods)->random()->value;

        // Matches SaleController@updatePayment flow
        $transaction->recordPayment($amount, $paymentType);

        if ($paymentType === TransactionTypeOfPaymentEnum::CASH->value) {
            app(CashOnHandService::class)->adjustBalance(
                $transaction->branch_id,
                $amount,
                'revenue'
            );
        }
    }

    private function seedSublimation($faker, $customerIds, $staff, $products, $date)
    {
        $particular = $faker->randomElement(array_keys($products));
        $subDesc = $faker->randomElement($products[$particular]);
        $transactionType = $faker->randomElement(['retail', 'purchase_order']);
        $status = $faker->randomElement(SublimationStatus::cases());

        $sublimation = Sublimation::query()->create([
            'branch_id' => $staff->branch_id,
            'customer_id' => $customerIds->random(),
            'user_id' => $staff->id,
            'status' => $status,
            'transaction_type' => $transactionType,
            'production_authorized' => $transactionType === 'retail' ? $faker->boolean(15) : false,
            'amount_total' => $faker->randomFloat(2, 1000, 5000),
            'description' => "Order: $subDesc",
            'quantity' => rand(10, 50),
            'due_at' => $date->clone()->addDays(9),
            'created_at' => $date,
            'updated_at' => $date,
        ]);

        $sublimationStatus = $sublimation->status;

        if (
            $sublimationStatus->isProductionPhase() ||
            $sublimationStatus === SublimationStatus::COMPLETED ||
            $sublimationStatus === SublimationStatus::DOWNPAYMENT_COMPLETE
        ) {
            $transaction = app(SalesService::class)->createTransaction([
                'description' => $sublimation->description,
                'branch_id' => $sublimation->branch_id,
                'customer_id' => $sublimation->customer_id,
                'invoice_number' => Transaction::generateNumber(),
                'amount_total' => $sublimation->amount_total,
                'particular' => 'Sublimation',
                'staff_id' => $sublimation->user_id,
                'transaction_date' => $date,
                'created_at' => $date,
                'updated_at' => $date,
            ]);

            $sublimation->update(['transaction_id' => $transaction->id]);
        }
    }
}
