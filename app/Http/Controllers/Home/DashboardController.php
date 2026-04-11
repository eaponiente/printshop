<?php

namespace App\Http\Controllers\Home;

use App\Enums\Sales\TransactionStatus;
use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Transaction;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function index(Request $request): Response
    {
        $now = Carbon::now();
        $startOfThisMonth = $now->copy()->startOfMonth();
        $startOfLastMonth = $now->copy()->subMonth()->startOfMonth();
        $endOfLastMonth = $now->copy()->subMonth()->endOfMonth();
        $today = $now->copy()->startOfDay();

        // 1. Total Revenue (amount_total of non-void transactions)
        $revenueThisMonth = Transaction::where('status', '!=', TransactionStatus::PENDING)
            ->whereBetween('transaction_date', [$startOfThisMonth, $now])
            ->sum('amount_total');
        $revenueLastMonth = Transaction::where('status', '!=', TransactionStatus::PENDING)
            ->whereBetween('transaction_date', [$startOfLastMonth, $endOfLastMonth])
            ->sum('amount_total');
        $revenueGrowth = $this->calculateGrowth($revenueThisMonth, $revenueLastMonth);

        // 2. New Customers (replacing Subscriptions)
        $customersThisMonth = Customer::whereBetween('created_at', [$startOfThisMonth, $now])->count();
        $customersLastMonth = Customer::whereBetween('created_at', [$startOfLastMonth, $endOfLastMonth])->count();
        $customersGrowth = $this->calculateGrowth($customersThisMonth, $customersLastMonth);

        // 3. Total Sales (Count of non-void transactions)
        $salesThisMonth = Transaction::where('status', '!=', TransactionStatus::PENDING)
            ->whereBetween('transaction_date', [$startOfThisMonth, $now])
            ->count();
        $salesLastMonth = Transaction::where('status', '!=', TransactionStatus::PENDING)
            ->whereBetween('transaction_date', [$startOfLastMonth, $endOfLastMonth])
            ->count();
        $salesGrowth = $this->calculateGrowth($salesThisMonth, $salesLastMonth);

        // 4. Pending Jobs (replacing Active Now)
        $totalPending = Transaction::whereIn('status', ['pending', 'partial'])->count();
        $pendingAddedToday = Transaction::whereIn('status', ['pending', 'partial'])
            ->where('created_at', '>=', $today)
            ->count();

        $stats = [
            'revenue' => [
                'value' => (float) $revenueThisMonth,
                'growth' => $revenueGrowth,
            ],
            'customers' => [
                'value' => $customersThisMonth,
                'growth' => $customersGrowth,
            ],
            'sales' => [
                'value' => $salesThisMonth,
                'growth' => $salesGrowth,
            ],
            'pending_jobs' => [
                'value' => $totalPending,
                'added_today' => $pendingAddedToday,
            ]
        ];

        // 5. Recent Transactions
        $recentTransactions = Transaction::with('customer')
            ->latest('transaction_date')
            ->take(5)
            ->get()
            ->map(fn ($t) => [
                'id' => $t->id,
                'amount_total' => (float) $t->amount_total,
                'customer_name' => $t->customer ? $t->customer->full_name : 'Walk-in',
                'customer_company' => $t->customer && $t->customer->company ? $t->customer->company : 'N/A',
            ]);

        // 6. Chart Data (Bar) - last 30 days
        $dailyData = Transaction::select(
            DB::raw("DATE_FORMAT(transaction_date, '%Y-%m-%d') as date"),
            DB::raw('SUM(amount_total) as total'),
            DB::raw('SUM(amount_paid) as paid')
        )
            ->where('status', '!=', TransactionStatus::PENDING)
            ->where('transaction_date', '>=', Carbon::now()->subDays(30))
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        // 7. Chart Data (Pie) - last 6 months
        $pieData = Transaction::select(
            DB::raw("DATE_FORMAT(transaction_date, '%M') as month"),
            DB::raw("DATE_FORMAT(transaction_date, '%Y-%m') as sort_date"),
            DB::raw('SUM(amount_total) as total')
        )
            ->where('status', '!=', TransactionStatus::PENDING)
            ->where('transaction_date', '>=', Carbon::now()->subMonths(6))
            ->groupBy('month', 'sort_date')
            ->orderBy('sort_date', 'asc')
            ->get()
            ->map(fn ($item) => [
                'month' => $item->month,
                'total' => (float) $item->total,
            ]);

        return Inertia::render('dashboard/dashboard', [
            'stats' => $stats,
            'recentTransactions' => $recentTransactions,
            'chartData' => $dailyData,
            'pieData' => $pieData,
        ]);
    }

    private function calculateGrowth($current, $previous): float
    {
        if ($previous == 0) {
            return $current > 0 ? 100 : 0;
        }
        return round((($current - $previous) / $previous) * 100, 1);
    }
}
