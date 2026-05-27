<?php

namespace Database\Factories;

use App\Enums\Sublimations\SublimationStatus;
use App\Models\Branch;
use App\Models\Customer;
use App\Models\Sublimation;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class SublimationFactory extends Factory
{
    protected $model = Sublimation::class;

    public function definition(): array
    {
        return [
            'branch_id' => Branch::factory(),
            'customer_id' => Customer::factory(),
            'user_id' => User::factory(),
            'amount_total' => $this->faker->randomFloat(2, 100, 5000),
            'status' => SublimationStatus::FOR_APPROVAL->value,
            'description' => $this->faker->sentence(),
            'due_at' => now()->addDays(7),
            'transaction_type' => 'retail',
            'production_authorized' => false,
            'quantity' => $this->faker->numberBetween(1, 100),
        ];
    }
}
