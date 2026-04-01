<?php

namespace App\Services\Sales;

use App\Models\Customer;
use App\Models\Expense;
use App\Models\Transaction;

class SalesService
{
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
