<?php

namespace Database\Factories;

use App\Models\Sublimation;
use App\Enums\Sublimations\SublimationStatus;
use Illuminate\Database\Eloquent\Factories\Factory;

class SublimationFactory extends Factory
{
    protected $model = Sublimation::class;

    public function definition(): array
    {
        return [
            'branch_id' => \App\Models\Branch::factory(),
            'customer_id' => \App\Models\Customer::factory(),
            'user_id' => \App\Models\User::factory(),
            'amount_total' => $this->faker->randomFloat(2, 100, 5000),
            'status' => SublimationStatus::FOR_APPROVAL->value,
            'description' => $this->faker->sentence(),
            'due_at' => now()->addDays(7),
            'transaction_type' => 'retail',
            'production_authorized' => false,
        ];
    }
}
