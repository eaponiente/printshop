<?php

namespace Database\Factories;

use App\Models\Branch;
use App\Models\CashOnHand;
use Illuminate\Database\Eloquent\Factories\Factory;

class CashOnHandFactory extends Factory
{
    protected $model = CashOnHand::class;

    public function definition(): array
    {
        return [
            'branch_id' => Branch::factory(),
            'amount' => fake()->randomFloat(2, 0, 10000),
        ];
    }
}
