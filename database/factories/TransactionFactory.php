<?php

namespace Database\Factories;

use App\Models\Transaction;
use App\Enums\Sales\TransactionStatus;
use Illuminate\Database\Eloquent\Factories\Factory;

class TransactionFactory extends Factory
{
    protected $model = Transaction::class;

    public function definition(): array
    {
        $amount_total = $this->faker->randomFloat(2, 50, 1000);
        
        return [
            'invoice_number' => 'INV-' . $this->faker->unique()->randomNumber(5, true),
            'customer_id' => \App\Models\Customer::factory(),
            'particular' => $this->faker->sentence(3),
            'description' => $this->faker->sentence(),
            'amount_total' => $amount_total,
            'amount_paid' => 0,
            'status' => TransactionStatus::PENDING->value,
            'staff_id' => \App\Models\User::factory(),
            'branch_id' => \App\Models\Branch::factory(),
            'transaction_date' => now(),
        ];
    }
}
