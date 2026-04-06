<?php

namespace Database\Seeders;

use App\Enums\Shared\TypeOfPaymentEnum;
use App\Models\Branch;
use App\Models\Customer;
use App\Models\Transaction;
use App\Models\User;
use App\Services\Sales\CashOnHandService;
use Illuminate\Database\Seeder;

class TransactionSeeder extends Seeder
{
    public function run(): void
    {
        // Eager load only what we need to save memory
        $customerIds = Customer::pluck('id');

        // We get the collection of staff/admins
        $staffMembers = User::whereIn('role', ['staff', 'admin'])
            ->get();

        $services = [
            ['name' => 'Room Accommodation', 'desc' => 'Deluxe Suite - 2 Nights Stay'],
            ['name' => 'Event Hall Rental', 'desc' => 'Function Room B - Half Day Seminar'],
            ['name' => 'Spa Treatment', 'desc' => 'Aromatherapy Massage (90 mins)'],
            ['name' => 'Restaurant Bill', 'desc' => 'Dinner Service - Table 12'],
            ['name' => 'Laundry Service', 'desc' => 'Express dry cleaning (5 items)'],
            ['name' => 'Airport Transfer', 'desc' => 'One-way shuttle service (Van)'],
        ];

        $totalToSeed = rand(300, 500);

        foreach (range(1, 50) as $i) {
            $service = fake()->randomElement($services);

            // 1. Financial Logic (Integers only)
            $amountTotal = match ($service['name']) {
                'Event Hall Rental' => fake()->numberBetween(2000, 8000),
                'Room Accommodation' => fake()->numberBetween(500, 1500),
                default => fake()->numberBetween(50, 450),
            };

            $roll = fake()->numberBetween(1, 100);

            if ($roll <= 60) {
                // 100% Paid
                $amountPaid = $amountTotal;
            } elseif ($roll <= 90) {
                // Partial Payment: 20% to 80% of total, rounded to no decimals
                $percentage = fake()->numberBetween(20, 80) / 100;
                $amountPaid = round($amountTotal * $percentage);
            } else {
                // 0% Paid
                $amountPaid = 0;
            }

            $status = match (true) {
                $amountPaid >= $amountTotal => 'paid',
                $amountPaid > 0 => 'partial',
                default => 'pending',
            };

            // 2. The Relationship Fix
            // Grab a single staff object from the collection
            $staff = $staffMembers->random();

            $paymentType = in_array($status, ['partial', 'paid']) ? fake()->randomElement(['cash', 'card', 'gcash', 'bank_transfer']) : null;

            $transaction = Transaction::create([
                'invoice_number' => Transaction::generateNumber(),
                'customer_id' => $customerIds->random(),
                'particular' => $service['name'],
                'description' => $service['desc'],
                'amount_total' => $amountTotal,
                'amount_paid' => 0,
                'payment_type' => $paymentType,
                'status' => 'pending',
                'staff_id' => $staff->id,        // Linked directly
                'branch_id' => $staff->branch_id, // Guaranteed to match staff's branch
                'transaction_date' => now()->subDays(rand(0, 30))->setTime(rand(7, 18), rand(0, 59)),
                'fulfilled_at' => null,
                'change_reason' => $status === 'partial' ? 'Guest promised to pay balance upon checkout.' : null,
            ]);

            if (in_array($status, ['partial', 'paid']) && $amountPaid > 0) {
                // To bypass auth()->id() checking in model (or if auth is missing)
                auth()->login($staff);

                $transaction->recordPayment($amountPaid, $paymentType);

                // Set the fulfilled_at for mock data if needed
                if ($status === 'paid') {
                    $transaction->update(['fulfilled_at' => now()->subMinutes(rand(1, 1000))]);
                }
            }

            if ($paymentType === TypeOfPaymentEnum::CASH->value) {
                app(CashOnHandService::class)->adjustBalance(
                    $transaction->branch_id,
                    $transaction->amount_paid,
                    'revenue'
                );
            }
        }
    }
}
