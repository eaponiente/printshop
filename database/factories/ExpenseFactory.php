<?php

namespace Database\Factories;

use App\Enums\Expenses\ExpenseStatus;
use App\Enums\Expenses\ExpenseTypeOfPaymentEnum;
use App\Models\Branch;
use App\Models\Expense;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class ExpenseFactory extends Factory
{
    protected $model = Expense::class;

    public function definition(): array
    {
        return [
            'description' => fake()->sentence(),
            'vendor_name' => fake()->company(),
            'amount' => fake()->randomFloat(2, 10, 5000),
            'status' => ExpenseStatus::PAID->value,
            'payment_type' => ExpenseTypeOfPaymentEnum::CASH->value,
            'user_id' => User::factory(),
            'branch_id' => Branch::factory(),
            'expense_date' => now(),
        ];
    }

    public function pending(): static
    {
        return $this->state(fn () => ['status' => ExpenseStatus::PENDING->value]);
    }

    public function paid(): static
    {
        return $this->state(fn () => ['status' => ExpenseStatus::PAID->value]);
    }

    public function rejected(): static
    {
        return $this->state(fn () => ['status' => ExpenseStatus::REJECTED->value]);
    }

    public function cash(): static
    {
        return $this->state(fn () => ['payment_type' => ExpenseTypeOfPaymentEnum::CASH->value]);
    }

    public function credit(): static
    {
        return $this->state(fn () => ['payment_type' => ExpenseTypeOfPaymentEnum::CREDIT->value]);
    }

    public function forBranch(Branch $branch): static
    {
        return $this->state(fn () => ['branch_id' => $branch->id]);
    }

    public function createdBy(User $user): static
    {
        return $this->state(fn () => ['user_id' => $user->id]);
    }

    public function crossBranchCredit(Branch $creditor, Branch $debtor): static
    {
        return $this->state(fn () => [
            'creditor_branch_id' => $creditor->id,
            'debtor_branch_id' => $debtor->id,
        ]);
    }
}
