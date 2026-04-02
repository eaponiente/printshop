<?php

namespace App\Services\Sales;

use App\Models\Customer;
use App\Models\Expense;
use App\Models\Transaction;
use App\Models\Payment;
use App\Models\CashOnHand;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class SalesService
{
    public function getTransactionQuery(array $filters): Builder
    {
        return Transaction::query()
            ->with(['user:id,first_name,last_name', 'branch:id,name', 'customer', 'payments'])
            ->filtered($filters)
            ->when($filters['status'] ?? null, fn ($q, $s) => $s !== 'all' ? $q->where('status', $s) : $q)
            ->when($filters['payment_type'] ?? null, function ($q, $s) {
                if ($s !== 'all') {
                    $q->whereHas('payments', fn ($sq) => $sq->where('payment_type', $s));
                }
            })
            ->latest('transaction_date');
    }

    public function getPaymentAggregates(Builder $query): array
    {
        // Join the payments table natively against the filtered transactions query
        $totals = Payment::query()
            ->joinSub((clone $query)->select('transactions.id')->reorder(), 't', 'payments.transaction_id', '=', 't.id')
            ->select('payments.payment_type', DB::raw('SUM(payments.amount) as total'))
            ->groupBy('payments.payment_type')
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
