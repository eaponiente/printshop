<?php

namespace Database\Factories;

use App\Models\RegisteredDevice;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class RegisteredDeviceFactory extends Factory
{
    protected $model = RegisteredDevice::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'device_token' => fake()->unique()->sha256(),
            'device_name' => fake()->word().' Device',
            'last_used_at' => now(),
            'is_active' => true,
            'is_approved' => false,
        ];
    }

    public function approved(): static
    {
        return $this->state(fn () => [
            'is_approved' => true,
            'approved_by' => User::factory(),
            'approved_at' => now(),
        ]);
    }
}
