<?php

namespace App\Services\Sales;

use App\Enums\Expenses\ExpenseStatus;
use App\Enums\Users\UserRole;
use App\Models\CashOnHand;
use App\Models\Customer;
use App\Models\Expense;
use App\Models\Payment;
use App\Models\Transaction;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class SalesService
{
    public function getTransactionQuery(array $filters): Builder
    {
        $user = auth()->user();

        $query = Transaction::query()
            ->with(['user:id,first_name,last_name', 'branch:id,name', 'customer', 'payments', 'sublimation'])
            ->where(function (Builder $q) use ($filters) {
                $this->applyDateFilter($q, 'transactions.transaction_date', $filters);
                $q->orWhereHas('payments', function (Builder $pq) use ($filters) {
                    $this->applyDateFilter($pq, 'payments.created_at', $filters);
                });
            })
            ->where(function (Builder $q) use ($filters, $user) {
                $filterId = $filters['branch_id'] ?? null;

                if ($user->role !== 'superadmin') {
                    $q->where('branch_id', $user->branch_id);
                } elseif ($filterId && $filterId !== 'all') {
                    $q->where('branch_id', $filterId);
                }
            })
            ->when($user->role === UserRole::STAFF->value, fn ($q) => $q->where('staff_id', $user->id))
            ->when($filters['search'] ?? null, function ($q, $s) {
                if ($s !== 'all') {
                    $q->where(function ($query) use ($s) {
                        $query->where('invoice_number', 'like', "%{$s}%")
                            ->orWhereHas('customer', function ($sq) use ($s) {
                                $sq->where('first_name', 'like', "%{$s}%")
                                    ->orWhere('last_name', 'like', "%{$s}%");
                            });
                    });
                }
            })
            ->when($filters['status'] ?? null, fn ($q, $s) => $s !== 'all' ? $q->where('status', $s) : $q)
            ->when($filters['payment_type'] ?? null, function ($q, $s) {
                if ($s !== 'all') {
                    $q->whereHas('payments', fn ($sq) => $sq->where('payment_type', $s));
                }
            });

        $sortField = $filters['sort_field'] ?? 'invoice_number';
        $sortDirection = $filters['sort_direction'] ?? 'desc';
        $allowedSorts = ['transaction_date'];

        if (in_array($sortField, $allowedSorts)) {
            $query->orderBy($sortField, $sortDirection === 'asc' ? 'asc' : 'desc');
        }

        return $query;
    }

    public function getPaymentAggregates(array|Builder $source): array
    {
        if ($source instanceof Builder) {
            $totals = Payment::query()
                ->joinSub((clone $source)->select('transactions.id')->reorder(), 't', 'payments.transaction_id', '=', 't.id')
                ->select('payments.payment_type', DB::raw('SUM(payments.amount) as total'))
                ->groupBy('payments.payment_type')
                ->pluck('total', 'payment_type')
                ->toArray();
        } else {
            $paymentsQuery = $this->buildPaymentDateQuery($source);

            $totals = (clone $paymentsQuery)
                ->select('payments.payment_type', DB::raw('SUM(payments.amount) as total'))
                ->groupBy('payments.payment_type')
                ->pluck('total', 'payment_type')
                ->toArray();
        }

        return [
            'total_sales' => (float) array_sum($totals),
            'gcash_amount' => (float) ($totals['gcash'] ?? 0),
            'card_amount' => (float) ($totals['card'] ?? 0),
            'check_amount' => (float) ($totals['check'] ?? 0),
            'bank_transfer_amount' => (float) ($totals['bank_transfer'] ?? 0),
            'cash_amount' => (float) ($totals['cash'] ?? 0),
            'debit_amount' => (float) ($totals['debit'] ?? 0),
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
        $revenue = (float) $this->buildPaymentDateQuery($filters)->sum('payments.amount');

        $expenses = (float) Expense::query()->filtered($filters)
            ->when($filters['payment_type'] ?? null, function ($q) use ($filters) {
                $q->where('payment_type', $filters['payment_type']);
            })
            ->where('status', ExpenseStatus::PAID->value)
            ->sum('amount');

        return [
            'total_expenses' => $expenses,
            'net_income' => $revenue - $expenses,
        ];
    }

    public function searchCustomers(?string $search)
    {
        return Customer::query()
            ->when($search, fn ($q, $t) => $q->whereAny(['first_name', 'last_name', 'company'], 'like', "%{$t}%"))
            ->limit(10)->get();
    }

    public function createTransaction($data)
    {
        return Transaction::create($data);
    }

    /**
     * Sum of only positive payment amounts (excludes refunds) for the period.
     */
    public function getGrossRevenue(array $filters): float
    {
        return (float) $this->buildPaymentDateQuery($filters)
            ->where('payments.amount', '>', 0)
            ->sum('payments.amount');
    }

    /**
     * Build a base payment query filtered by payment date and branch.
     */
    private function buildPaymentDateQuery(array $filters): Builder
    {
        $user = auth()->user();

        return Payment::query()
            ->join('transactions', 'payments.transaction_id', '=', 'transactions.id')
            ->whereNull('transactions.deleted_at')
            ->tap(function (Builder $q) use ($filters) {
                $this->applyDateFilter($q, 'payments.created_at', $filters);
            })
            ->where(function (Builder $q) use ($filters, $user) {
                $filterId = $filters['branch_id'] ?? null;

                if ($user->role !== 'superadmin') {
                    $q->where('transactions.branch_id', $user->branch_id);
                } elseif ($filterId && $filterId !== 'all') {
                    $q->where('transactions.branch_id', $filterId);
                }
            })
            ->when($user->role === UserRole::STAFF->value, function (Builder $q) use ($user) {
                $q->where('transactions.staff_id', $user->id);
            });
    }

    /**
     * Apply a date range filter to a given column on a query.
     */
    private function applyDateFilter(Builder $query, string $column, array $filters): void
    {
        $date = $filters['date'] ?? now()->toDateString();

        match ($filters['mode'] ?? 'daily') {
            'daily' => $query->whereDate($column, $date),
            'weekly' => (function () use ($query, $column, $date) {
                $start = Carbon::parse($date)->startOfWeek();
                $end = Carbon::parse($date)->endOfWeek();
                $query->whereBetween($column, [$start, $end]);
            })(),
            'monthly' => $query->whereMonth($column, Carbon::parse($date)->month)
                ->whereYear($column, Carbon::parse($date)->year),
            'yearly' => $query->whereYear($column, $date),
            default => $query->whereDate($column, now()->toDateString()),
        };
    }
}
