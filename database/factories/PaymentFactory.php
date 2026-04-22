<?php

namespace Database\Factories;

use App\Models\Payment;
use Illuminate\Database\Eloquent\Factories\Factory;

class PaymentFactory extends Factory
{
    protected $model = Payment::class;

    public function definition(): array
    {
        return [
            'transaction_id' => \App\Models\Transaction::factory(),
            'amount' => $this->faker->randomFloat(2, 10, 100),
            'payment_type' => 'cash',
            'staff_id' => \App\Models\User::factory(),
        ];
    }
}
