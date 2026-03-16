<?php

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

class TransactionSeeder extends Seeder
{
    public function run(): void
    {
        // Fetch existing IDs based on your logic
        $branchIds = Branch::pluck('id');
        $staffIds = User::whereIn('role', ['staff', 'admin'])->pluck('id');

        // Fallback check: If your DB is empty, this prevents the seeder from crashing
        if ($branchIds->isEmpty() || $staffIds->isEmpty()) {
            $this->command->warn('Skipping TransactionSeeder: No branches or staff/admins found in database.');

            return;
        }

        $services = [
            ['name' => 'Room Accommodation', 'desc' => 'Deluxe Suite - 2 Nights Stay'],
            ['name' => 'Event Hall Rental', 'desc' => 'Function Room B - Half Day Seminar'],
            ['name' => 'Spa Treatment', 'desc' => 'Aromatherapy Massage (90 mins)'],
            ['name' => 'Restaurant Bill', 'desc' => 'Dinner Service - Table 12'],
            ['name' => 'Laundry Service', 'desc' => 'Express dry cleaning (5 items)'],
            ['name' => 'Airport Transfer', 'desc' => 'One-way shuttle service (Van)'],
        ];

        foreach (range(1, 100) as $i) {
            $service = fake()->randomElement($services);

            // Generate realistic amounts based on service type
            $amountTotal = match ($service['name']) {
                'Event Hall Rental' => fake()->randomFloat(2, 2000, 8000),
                'Room Accommodation' => fake()->randomFloat(2, 500, 1500),
                default => fake()->randomFloat(2, 50, 450),
            };

            // Logic: 60% paid, 30% partial, 10% pending (0 paid)
            $roll = fake()->numberBetween(1, 100);
            if ($roll <= 60) {
                $amountPaid = $amountTotal;
            } elseif ($roll <= 90) {
                $amountPaid = floor($amountTotal * fake()->randomFloat(2, 0.2, 0.7)); // 20-70% deposit
            } else {
                $amountPaid = 0;
            }

            $balance = $amountTotal - $amountPaid;

            // Determine status
            $status = 'pending';
            if ($balance == 0) {
                $status = 'paid';
            } elseif ($amountPaid > 0) {
                $status = 'partial';
            }

            Transaction::create([
                'invoice_number' => 'INV-'.date('Y').'-'.str_pad($i + 1000, 6, '0', STR_PAD_LEFT),
                'guest_name' => fake()->name(),
                'particular' => $service['name'],
                'description' => $service['desc'],
                'amount_total' => $amountTotal,
                'amount_paid' => $amountPaid,
                'balance' => $balance,
                'payment_type' => fake()->randomElement(['Cash', 'Credit Card', 'GCash', 'PayMaya', 'Bank Transfer']),
                'status' => $status,
                'staff_id' => $staffIds->random(), // Pick random staff from your pluck
                'branch_id' => $branchIds->random(), // Pick random branch from your pluck
                'transaction_date' => Carbon::now()->subDays(fake()->numberBetween(0, 90))->setTime(rand(7, 18), rand(0, 59), rand(0, 59)),
                'fulfilled_at' => $status === 'paid' ? Carbon::now()->subMinutes(fake()->numberBetween(1, 1000)) : null,
                'change_reason' => $status === 'partial' ? 'Guest promised to pay balance upon checkout.' : null,
            ]);
        }
    }
}
