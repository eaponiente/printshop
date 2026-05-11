<?php

namespace Database\Factories;

use App\Enums\Incentives\IncentiveStatus;
use App\Models\Branch;
use App\Models\Incentive;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class IncentiveFactory extends Factory
{
    protected $model = Incentive::class;

    public function definition(): array
    {
        return [
            'branch_id' => Branch::factory(),
            'user_id' => User::factory(),
            'month' => now()->month,
            'year' => now()->year,
            'net_income' => fake()->randomFloat(2, 1000, 50000),
            'incentive_amount' => fake()->randomFloat(2, 50, 2500),
            'status' => IncentiveStatus::PAID->value,
            'paid_at' => now(),
        ];
    }

    public function pending(): static
    {
        return $this->state(fn () => [
            'status' => IncentiveStatus::PENDING->value,
            'paid_at' => null,
        ]);
    }

    public function paid(): static
    {
        return $this->state(fn () => [
            'status' => IncentiveStatus::PAID->value,
            'paid_at' => now(),
        ]);
    }

    public function forBranch(Branch $branch): static
    {
        return $this->state(fn () => ['branch_id' => $branch->id]);
    }

    public function forUser(User $user): static
    {
        return $this->state(fn () => ['user_id' => $user->id]);
    }
}
