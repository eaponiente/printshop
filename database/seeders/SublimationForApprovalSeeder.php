<?php

namespace Database\Seeders;

use App\Enums\Sublimations\SublimationStatus;
use App\Models\Branch;
use App\Models\Customer;
use App\Models\Sublimation;
use App\Models\User;
use Illuminate\Database\Seeder;

class SublimationForApprovalSeeder extends Seeder
{
    public function run(): void
    {
        $branches = Branch::all();
        $customers = Customer::all();
        $users = User::all();

        if ($branches->isEmpty() || $customers->isEmpty() || $users->isEmpty()) {
            $this->command?->warn('Run BranchSeeder, CustomerSeeder, and UsersSeeder before SublimationForApprovalSeeder.');

            return;
        }

        for ($i = 0; $i < 15; $i++) {
            Sublimation::factory()->create([
                'branch_id' => $branches->random()->id,
                'customer_id' => $customers->random()->id,
                'user_id' => $users->random()->id,
                'status' => SublimationStatus::FOR_APPROVAL,
                'production_authorized' => false,
                'due_at' => now()->addDays(fake()->numberBetween(3, 14)),
            ]);
        }
    }
}
