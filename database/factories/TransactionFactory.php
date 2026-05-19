<?php

namespace Database\Factories;

use App\Enums\Sales\TransactionStatus;
use App\Models\Branch;
use App\Models\Customer;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class TransactionFactory extends Factory
{
    protected $model = Transaction::class;

    public function definition(): array
    {
        $amount_total = $this->faker->randomFloat(2, 50, 1000);

        return [
            'invoice_number' => 'INV-'.$this->faker->unique()->randomNumber(5, true),
            'customer_id' => Customer::factory(),
            'particular' => $this->faker->sentence(3),
            'description' => $this->faker->sentence(),
            'amount_total' => $amount_total,
            'amount_paid' => 0,
            'status' => TransactionStatus::PENDING->value,
            'staff_id' => User::factory(),
            'branch_id' => Branch::factory(),
            'transaction_date' => now(),
        ];
    }
}
