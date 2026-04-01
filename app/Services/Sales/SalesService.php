<?php

namespace App\Services\Sales;

use App\Models\Customer;
use App\Models\Expense;
use App\Models\Transaction;
use App\Models\CashOnHand;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class SalesService
{
    public function getTransactionQuery(array $filters): Builder
    {
        return Transaction::query()
            ->with(['user:id,first_name,last_name', 'branch:id,name', 'customer'])
            ->filtered($filters)
            ->when($filters['status'] ?? null, fn ($q, $s) => $s !== 'all' ? $q->where('status', $s) : $q)
            ->when($filters['payment_type'] ?? null, fn ($q, $s) => $s !== 'all' ? $q->where('payment_type', $s) : $q)
            ->latest('transaction_date');
    }

    public function getPaymentAggregates(Builder $query): array
    {
        // Run an optimized grouped query to avoid executing 6 separate DB queries
        $totals = (clone $query)
            ->without(['user', 'branch', 'customer'])
            ->reorder()
            ->select('payment_type', DB::raw('SUM(amount_paid) as total'))
            ->groupBy('payment_type')
            ->pluck('total', 'payment_type')
            ->toArray();

        return [
            'total_sales' => (float) array_sum($totals),
            'gcash_amount' => (float) ($totals['gcash'] ?? 0),
            'card_amount' => (float) ($totals['card'] ?? 0),
            'check_amount' => (float) ($totals['check'] ?? 0),
            'bank_transfer_amount' => (float) ($totals['bank_transfer'] ?? 0),
            'cash_amount' => (float) ($totals['cash'] ?? 0),
        ];
    }

    public function getCashOnHandTotal(?string $branchId): float
    {
        return (float) CashOnHand::query()
            ->when($branchId && $branchId !== 'all', fn ($q) => $q->where('branch_id', $branchId))
            ->sum('amount');
    }

    public function getFinanceSummary(array $filters): array
    {
        $revenue = Transaction::query()->filtered($filters)->sum('amount_paid');
        $expenses = Expense::query()->filtered($filters)
            ->when($filters['payment_type'] ?? null, function ($q) use ($filters) {
                $q->where('payment_type', $filters['payment_type']);
            })
            ->sum('amount');

        return [
            'total_expenses' => (float) $expenses,
            'net_income' => (float) ($revenue - $expenses),
        ];
    }

    public function searchCustomers(?string $search)
    {
        return Customer::query()
            ->when($search, fn ($q, $t) => $q->whereAny(['first_name', 'last_name', 'company'], 'like', "%{$t}%"))
            ->limit(10)->get();
    }
}
