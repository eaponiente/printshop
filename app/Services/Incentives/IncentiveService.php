<?php

namespace App\Services\Incentives;

use App\Enums\Expenses\ExpenseStatus;
use App\Enums\Incentives\IncentiveStatus;
use App\Enums\Users\UserRole;
use App\Models\Branch;
use App\Models\CashOnHand;
use App\Models\Expense;
use App\Models\Incentive;
use App\Models\Payment;
use App\Models\User;
use App\Services\Sales\CashOnHandService;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class IncentiveService
{
    public function getBranchIncentives(int $month, int $year, ?string $branchId = null): Collection
    {
        $branches = Branch::query()
            ->when(auth()->user()->isSuperAdmin() && $branchId && $branchId !== 'all',
                fn ($q) => $q->where('id', $branchId))
            ->when(! auth()->user()->isSuperAdmin(),
                fn ($q) => $q->where('id', auth()->user()->branch_id))
            ->get()
            ->keyBy('id');

        $incentives = Incentive::query()
            ->where('month', $month)
            ->where('year', $year)
            ->when($branchId && $branchId !== 'all',
                fn ($q) => $q->where('branch_id', $branchId))
            ->get()
            ->keyBy(fn (Incentive $i) => $i->branch_id.'-'.$i->user_id);

        $admins = User::query()
            ->where('role', UserRole::ADMIN->value)
            ->whereIn('branch_id', $branches->keys())
            ->get();

        $date = Carbon::create($year, $month, 1)->startOfMonth();
        $monthEnd = $date->copy()->endOfMonth();

        $branchFinances = [];
        foreach ($branches as $branch) {
            $revenue = (float) Payment::query()
                ->whereHas('transaction', fn ($q) => $q->where('branch_id', $branch->id))
                ->where('amount', '>', 0)
                ->whereBetween('created_at', [$date, $monthEnd])
                ->sum('amount');

            $expenses = (float) Expense::query()
                ->where('branch_id', $branch->id)
                ->where('status', ExpenseStatus::PAID->value)
                ->whereBetween('expense_date', [$date->format('Y-m-d'), $monthEnd->format('Y-m-d')])
                ->sum('amount');

            $cashOnHand = (float) CashOnHand::query()
                ->where('branch_id', $branch->id)
                ->value('amount') ?? 0;

            $branchFinances[$branch->id] = [
                'revenue' => $revenue,
                'expenses' => $expenses,
                'net_income' => round($revenue - $expenses, 2),
                'cash_on_hand' => $cashOnHand,
            ];
        }

        return $admins->map(function (User $admin) use ($month, $year, $branchFinances, $branches, $incentives) {
            $branch = $branches->get($admin->branch_id);
            $finance = $branchFinances[$admin->branch_id];
            $key = $admin->branch_id.'-'.$admin->id;
            $existing = $incentives->get($key);

            return [
                'id' => $existing?->id,
                'branch_id' => $admin->branch_id,
                'branch_name' => $branch?->name,
                'manager_name' => $admin->fullname,
                'manager_id' => $admin->id,
                'month' => $month,
                'year' => $year,
                'revenue' => $finance['revenue'],
                'expenses' => $finance['expenses'],
                'net_income' => $finance['net_income'],
                'incentive_amount' => (float) ($existing?->incentive_amount ?? 0),
                'owner_contribution' => (float) ($existing?->owner_contribution ?? 0),
                'cash_on_hand' => $finance['cash_on_hand'],
                'status' => $existing?->status ?? 'uncomputed',
                'paid_at' => $existing?->paid_at,
            ];
        })->values();
    }

    public function payIncentive(int $branchId, int $userId, int $month, int $year, float $incentiveAmount): Incentive
    {
        return DB::transaction(function () use ($branchId, $userId, $month, $year, $incentiveAmount) {
            $date = Carbon::create($year, $month, 1)->startOfMonth();
            $monthEnd = $date->copy()->endOfMonth();

            $revenue = (float) Payment::query()
                ->whereHas('transaction', fn ($q) => $q->where('branch_id', $branchId))
                ->where('amount', '>', 0)
                ->whereBetween('created_at', [$date, $monthEnd])
                ->sum('amount');

            $expenses = (float) Expense::query()
                ->where('branch_id', $branchId)
                ->where('status', ExpenseStatus::PAID->value)
                ->whereBetween('expense_date', [$date->format('Y-m-d'), $monthEnd->format('Y-m-d')])
                ->sum('amount');

            $netIncome = $revenue - $expenses;

            $currentCash = (float) CashOnHand::query()
                ->where('branch_id', $branchId)
                ->value('amount') ?? 0;

            $cashPortion = min($currentCash, $incentiveAmount);
            $ownerContribution = round($incentiveAmount - $cashPortion, 2);

            if ($cashPortion > 0) {
                app(CashOnHandService::class)->adjustBalance(
                    $branchId,
                    $cashPortion,
                    'expense'
                );
            }

            $incentive = Incentive::updateOrCreate(
                ['branch_id' => $branchId, 'user_id' => $userId, 'month' => $month, 'year' => $year],
                [
                    'net_income' => $netIncome,
                    'incentive_amount' => $incentiveAmount,
                    'owner_contribution' => $ownerContribution,
                    'status' => IncentiveStatus::PAID->value,
                    'paid_at' => now(),
                ]
            );

            return $incentive;
        });
    }

    public function getIncentiveHistory(?string $branchId = null): Collection
    {
        return Incentive::query()
            ->with(['branch:id,name', 'user:id,first_name,last_name'])
            ->where('status', IncentiveStatus::PAID->value)
            ->when(auth()->user()->isSuperAdmin(),
                fn ($q) => $q->when($branchId && $branchId !== 'all',
                    fn ($q2) => $q2->where('branch_id', $branchId)))
            ->when(! auth()->user()->isSuperAdmin(),
                fn ($q) => $q->where('branch_id', auth()->user()->branch_id))
            ->orderByDesc('year')
            ->orderByDesc('month')
            ->get();
    }
}
