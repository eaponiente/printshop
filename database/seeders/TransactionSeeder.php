<?php

namespace Database\Seeders;

use App\Enums\Shared\TypeOfPaymentEnum;
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

        // Data for Standard Transactions
        $standardServices = [
            ['name' => 'Room Accommodation', 'desc' => 'Deluxe Suite - 2 Nights Stay'],
            ['name' => 'Event Hall Rental', 'desc' => 'Function Room B - Half Day Seminar'],
            ['name' => 'Spa Treatment', 'desc' => 'Aromatherapy Massage (90 mins)'],
            ['name' => 'Restaurant Bill', 'desc' => 'Dinner Service - Table 12'],
        ];

        // Data for Sublimation
        $sublimationProducts = [
            'Full Sublimation Jersey' => ['Basketball set', 'Volleyball uniform'],
            'Corporate Lanyard' => ['1-inch nylon', 'Digital print'],
            'Custom Hoodie' => ['Pullover with back print'],
        ];

        // Total records to create (e.g., 400 standard + 40 sublimation)
        $iterations = range(1, 450);

        foreach ($iterations as $i) {
            $staff = $staffMembers->random();
            auth()->login($staff); // Ensure service logic has a user context

            // Randomly decide: 90% chance for Standard, 10% chance for Sublimation
            if ($faker->boolean(10)) {
                $this->seedSublimation($faker, $customerIds, $staff, $sublimationProducts);
            } else {
                $this->seedStandardTransaction($faker, $customerIds, $staff, $standardServices);
            }
        }
    }

    private function seedStandardTransaction($faker, $customerIds, $staff, $services)
    {
        $service = $faker->randomElement($services);
        $amountTotal = ($service['name'] === 'Event Hall Rental') ? rand(2000, 8000) : rand(50, 500);

        $roll = rand(1, 100);
        $amountPaid = $roll <= 60 ? $amountTotal : ($roll <= 90 ? round($amountTotal * (rand(20, 80) / 100)) : 0);
        $status = $amountPaid >= $amountTotal ? 'paid' : ($amountPaid > 0 ? 'partial' : 'pending');
        $paymentType = in_array($status, ['partial', 'paid']) ? $faker->randomElement(['cash', 'card', 'gcash']) : null;

        $transactionDate = [now()->subDays(rand(0, 7)), now()->addDays(rand(0, 10))];
        $transaction = Transaction::create([
            'invoice_number' => Transaction::generateNumber(),
            'customer_id' => $customerIds->random(),
            'particular' => $service['name'],
            'description' => $service['desc'],
            'amount_total' => $amountTotal,
            'amount_paid' => 0,
            'payment_type' => $paymentType,
            'status' => 'pending',
            'staff_id' => $staff->id,
            'branch_id' => $staff->branch_id,
            'transaction_date' => $transactionDate[array_rand($transactionDate)],
            'created_at' => $transactionDate[array_rand($transactionDate)],
            'updated_at' => $transactionDate[array_rand($transactionDate)],
        ]);

        if ($amountPaid > 0) {
            $transaction->recordPayment($amountPaid, $paymentType);
            if ($paymentType === TypeOfPaymentEnum::CASH->value) {
                app(CashOnHandService::class)->adjustBalance($transaction->branch_id, $amountPaid, 'revenue');
            }
        }
    }

    private function seedSublimation($faker, $customerIds, $staff, $products)
    {
        $particular = $faker->randomElement(array_keys($products));
        $subDesc = $faker->randomElement($products[$particular]);
        $transactionType = $faker->randomElement(['retail', 'purchase_order']);
        $status = $faker->randomElement(SublimationStatus::cases());

        $transactionDate = [now()->subDays(rand(0, 7)), now()->addDays(rand(0, 10))];


        $sublimation = Sublimation::query()->create([
            'branch_id' => $staff->branch_id,
            'customer_id' => $customerIds->random(),
            'user_id' => $staff->id,
            'status' => $status,
            'transaction_type' => $transactionType,
            'production_authorized' => ($transactionType === 'retail') ? $faker->boolean(15) : false,
            'amount_total' => $faker->randomFloat(2, 1000, 5000),
            'description' => "Order: $subDesc",
            'due_at' => Carbon::parse($transactionDate[array_rand($transactionDate)])->addDays(9),
            'created_at' => $transactionDate[array_rand($transactionDate)],
            'updated_at' => $transactionDate[array_rand($transactionDate)],
        ]);

        $sublimationStatus = $sublimation->status;

        if ($sublimationStatus->isProductionPhase() ||
            $sublimationStatus === SublimationStatus::COMPLETED->value ||
            $sublimationStatus === SublimationStatus::DOWNPAYMENT_COMPLETE->value) {
            $transaction = app(SalesService::class)->createTransaction([
                'description' => $sublimation->description,
                'branch_id' => $sublimation->branch_id,
                'customer_id' => $sublimation->customer_id,
                'invoice_number' => Transaction::generateNumber(),
                'amount_total' => $sublimation->amount_total,
                'particular' => 'Sublimation',
                'staff_id' => $sublimation->user_id,
                'transaction_date' => $transactionDate[array_rand($transactionDate)],
                'created_at' => $transactionDate[array_rand($transactionDate)],
                'updated_at' => $transactionDate[array_rand($transactionDate)],
            ]);

            $sublimation->update(['transaction_id' => $transaction->id]);
        }
    }
}
