<?php

namespace Database\Seeders;

use App\Enums\Sublimations\SublimationStatus;
use App\Models\Customer;
use App\Models\Sublimation;
use App\Models\Transaction;
use App\Models\User;
use App\Services\Sales\SalesService;
use Carbon\Carbon;
use Faker\Factory as Faker;
use Illuminate\Database\Seeder;

class SublimationSeeder extends Seeder
{
    public function run(): void
    {
        $faker = Faker::create();
        $customerIds = Customer::pluck('id');
        $users = User::whereIn('role', ['staff', 'admin'])->get();

        $products = [
            'Full Sublimation Jersey' => ['Basketball set', 'Volleyball uniform', 'eSports jersey'],
            'Corporate Lanyard' => ['1-inch nylon', 'Smooth polyester', 'Digital print'],
            'Custom Hoodie' => ['Pullover with back print', 'Zip-up with chest logo'],
            'Campaign Paraphernalia' => ['Vinyl Sticker', 'Foldable Hand Fan', 'Button Pins'],
        ];

        foreach (range(1, 40) as $index) {
            $particular = $faker->randomElement(array_keys($products));
            $subDesc = $faker->randomElement($products[$particular]);

            // 1. Determine Transaction Type (80% Retail, 20% PO)
            $transactionType = $faker->randomElement(['retail', 'purchase_order']);

            // 2. Determine if it has a manual override (rare case for retail)
            $isAuthorized = ($transactionType === 'retail') ? $faker->boolean(15) : false;

            // 3. Logic-based Status Selection
            if ($transactionType === 'purchase_order' || $isAuthorized) {
                // POs and Authorized orders can be any status
                $status = $faker->randomElement(SublimationStatus::cases());
            } else {
                // Retail without override: 60% are still in pre-production or just paid
                // 40% are allowed to be in production (simulating they have payments)
                $status = $faker->randomElement(SublimationStatus::cases());

                if ($status->isProductionPhase()) {
                    // Force the status back to a gate if we want to simulate a "blocked" order
                    // Or we keep it as is, implying the logic check happens during the update,
                    // but for a clean seeder, let's make it look valid:
                    $status = $faker->boolean(70) ? $status : SublimationStatus::WAITING_FOR_DP;
                }
            }

            $user = $users->random();
            $totalAmount = $faker->randomFloat(2, 1000, 5000);
            $dueAt = now()->subDays(15)->addDays(rand(0, 30));
            $dateCreated = Carbon::parse($dueAt)->subDays(rand(2, 7));

            $sublimation = Sublimation::create([
                'branch_id' => $user->branch_id,
                'customer_id' => $customerIds->random(),
                'user_id' => $user->id,
                'status' => $status,
                'transaction_type' => $transactionType,
                'production_authorized' => $isAuthorized,
                'amount_total' => $totalAmount,
                'notes' => $faker->words(7, true),
                'description' => "Order for {$faker->numberBetween(10, 100)} pcs: $subDesc. " . $faker->sentence(),
                'due_at' => $dueAt,
                'created_at' => $dateCreated,
            ]);

            // Create the associated Sales Transaction
            $transactionData = $sublimation->only(['description', 'branch_id', 'customer_id']);
            $transaction = app(SalesService::class)->createTransaction(array_merge($transactionData, [
                'invoice_number' => Transaction::generateNumber(),
                'amount_total' => $sublimation->amount_total,
                'particular' => 'Sublimation',
                'staff_id' => $sublimation->user_id,
                'transaction_date' => $dateCreated,
                'created_at' => $dateCreated,
            ]));

            $sublimation->update(['transaction_id' => $transaction->id]);
        }
    }
}
