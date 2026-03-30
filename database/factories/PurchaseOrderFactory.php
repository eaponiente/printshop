<?php

namespace Database\Factories;

use App\Models\Branch;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class PurchaseOrderFactory extends Factory
{
    public function definition(): array
    {
        return [
            // Using a random element from a larger set for 'particular'
            'po_number' => 'PO-'.$this->faker->unique()->numerify('#####'),

            // Description now looks like a real order note
            'description' => $this->faker->realTextBetween(20, 160),

            // Status distribution (weighted towards pending/active is usually more realistic)
            'status' => $this->faker->randomElement(['pending', 'active', 'finished', 'released']),

            'grand_total' => 0,

            // Random timestamp within the last 30 days
            'ordered_at' => $this->faker->dateTimeBetween('-30 days', 'now'),

            // Adding timestamps if your table has them
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }
}
