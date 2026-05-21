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
        $query = Transaction::query()
            ->with(['user:id,first_name,last_name', 'branch:id,name', 'customer', 'payments', 'sublimation'])
            ->dateFiltered($filters)
            ->branchFilters($filters)
            ->when(auth()->user()->role === UserRole::STAFF->value, function ($q) {
                return $q->where('staff_id', auth()->id());
            })
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
            })
            ->when($filters['staff_id'] ?? null, function ($q, $s) {
                if ($s !== 'all') {
                    $q->where('staff_id', $s);
                }
            });

        // 2. Sorting Logic
        $sortField = $filters['sort_field'] ?? 'invoice_number'; // Default sort
        $sortDirection = $filters['sort_direction'] ?? 'desc';

        // Whitelist allowed sortable columns
        $allowedSorts = ['transaction_date'];

        if (in_array($sortField, $allowedSorts)) {
            $query->orderBy($sortField, $sortDirection === 'asc' ? 'asc' : 'desc');
        }

        return $query;
    }

    /**
     * Query payments with their parent transaction data,
     * filtered by payment created_at date range.
     * Excludes negative (refund) payment entries.
     */
    public function getPaymentQuery(array $filters): Builder
    {
        $user = auth()->user();

        $query = Payment::query()
            ->with([
                'transaction' => function ($q) {
                    $q->with(['user:id,first_name,last_name', 'branch:id,name', 'customer', 'payments', 'sublimation']);
                },
                'staff:id,first_name,last_name',
            ])
            ->where('payments.amount', '>', 0)
            ->whereHas('transaction', function ($q) use ($user, $filters) {
                $filterId = $filters['branch_id'] ?? null;

                // Exclude fully-refunded transactions (amount_paid back to 0)
                $q->where('amount_paid', '>', 0);

                if ($user->role !== 'superadmin') {
                    $q->where('branch_id', $user->branch_id);
                } elseif ($filterId && $filterId !== 'all') {
                    $q->where('branch_id', $filterId);
                }
            })
            ->when($filters['status'] ?? null, function ($q, $s) {
                if ($s !== 'all') {
                    $q->whereHas('transaction', fn ($sq) => $sq->where('status', $s));
                }
            })
            ->when($filters['payment_type'] ?? null, function ($q, $s) {
                if ($s !== 'all') {
                    $q->where('payment_type', $s);
                }
            })
            ->when($filters['search'] ?? null, function ($q, $s) {
                $q->whereHas('transaction', function ($sq) use ($s) {
                    $sq->where('invoice_number', 'like', "%{$s}%")
                        ->orWhereHas('customer', function ($sq2) use ($s) {
                            $sq2->where('first_name', 'like', "%{$s}%")
                                ->orWhere('last_name', 'like', "%{$s}%");
                        });
                });
            });

        // Apply date filter on payments.created_at
        $date = $filters['date'] ?? now()->toDateString();

        match ($filters['mode'] ?? 'daily') {
            'daily' => $query->whereDate('payments.created_at', $date),
            'weekly' => $query->whereBetween('payments.created_at', [
                Carbon::parse($date)->startOfWeek(),
                Carbon::parse($date)->endOfWeek(),
            ]),
            'monthly' => $query->whereMonth('payments.created_at', Carbon::parse($date)->month)
                ->whereYear('payments.created_at', Carbon::parse($date)->year),
            'yearly' => $query->whereYear('payments.created_at', $date),
            default => $query->whereDate('payments.created_at', now()->toDateString()),
        };

        // Staff restriction
        if ($user->role === UserRole::STAFF->value) {
            $query->whereHas('transaction', fn ($q) => $q->where('staff_id', $user->id));
        }

        // Optional staff_id filter (superadmin only)
        $staffId = $filters['staff_id'] ?? null;
        if ($staffId && $staffId !== 'all') {
            $query->whereHas('transaction', fn ($q) => $q->where('staff_id', $staffId));
        }

        // Sorting
        $sortField = $filters['sort_field'] ?? 'created_at';
        $sortDirection = $filters['sort_direction'] ?? 'desc';
        $sortColumn = $sortField === 'transaction_date' ? 'payments.created_at' : 'payments.created_at';
        $query->orderBy($sortColumn, $sortDirection === 'asc' ? 'asc' : 'desc');

        return $query;
    }

    public function getPaymentAggregatesFromPayments(Builder $paymentQuery, $filters): array
    {
        $totals = (clone $paymentQuery)
            ->reorder()
            ->select('payments.payment_type', DB::raw('SUM(payments.amount) as total'))
            ->groupBy('payments.payment_type')
            ->pluck('total', 'payment_type')
            ->toArray();

        $cashNet = (float) (($totals['cash'] ?? 0) - $this->getExpenseTotalForType('cash', $filters));
        $gcashNet = (float) (($totals['gcash'] ?? 0) - $this->getExpenseTotalForType('gcash', $filters));

        return [
            'total_sales' => (float) array_sum($totals),
            'gcash_amount' => (float) ($totals['gcash'] ?? 0),
            'card_amount' => (float) ($totals['card'] ?? 0),
            'check_amount' => (float) ($totals['check'] ?? 0),
            'bank_transfer_amount' => (float) ($totals['bank_transfer'] ?? 0),
            'cash_amount' => (float) ($totals['cash'] ?? 0),
            'debit_amount' => (float) ($totals['debit'] ?? 0),
            'cash_net_amount' => $cashNet,
            'gcash_net_amount' => $gcashNet,
        ];
    }

    private function getExpenseTotalForType(string $paymentType, array $filters): float
    {
        return (float) Expense::query()
            ->dateFiltered($filters)
            ->salesBranchFilters($filters)
            ->where('payment_type', $paymentType)
            ->where('status', ExpenseStatus::PAID->value)
            ->sum('amount');
    }

    public function getCashOnHandTotal(?string $branchId): float
    {
        return (float) CashOnHand::query()
            ->when($branchId && $branchId !== 'all', fn ($q) => $q->where('branch_id', $branchId))
            ->sum('amount');
    }

    /**
     * Calculate finance summary based on actual payments within the date range,
     * not the full transaction amounts.
     */
    public function getFinanceSummaryFromPayments(Builder $paymentQuery, array $filters): array
    {
        $revenue = (float) (clone $paymentQuery)->reorder()->sum('payments.amount');

        $expenses = Expense::query()
            ->dateFiltered($filters)
            ->salesBranchFilters($filters)
            ->when($filters['payment_type'] ?? null, function ($q) use ($filters) {
                $q->where('payment_type', $filters['payment_type']);
            })
            ->where('status', ExpenseStatus::PAID->value)
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

    public function createTransaction($data)
    {
        return Transaction::create($data);
    }
}
