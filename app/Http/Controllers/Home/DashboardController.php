<?php

namespace App\Http\Controllers\Home;

use App\Enums\Sales\TransactionStatus;
use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Transaction;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function index(Request $request): Response
    {
        // Enforce a 10-second max execution time on all queries in this request
        // to prevent slow or locked DB queries from hanging the PHP process and
        // causing 502 Bad Gateway errors.
        try {
            DB::statement('SET SESSION max_execution_time = 10000;');
        } catch (\Exception $e) {
            Log::warning('DashboardController: could not set max_execution_time — ' . $e->getMessage());
        }

        $now = Carbon::now();
        $startOfThisMonth = $now->copy()->startOfMonth();
        $startOfLastMonth = $now->copy()->subMonth()->startOfMonth();
        $endOfLastMonth = $now->copy()->subMonth()->endOfMonth();
        $today = $now->copy()->startOfDay();

        // Stats are cached for 5 minutes so a slow DB cannot block every page
        // load. The cache key is intentionally simple — stats are global, not
        // per-user.
        $stats = Cache::remember('dashboard.stats', 300, function () use (
            $now, $startOfThisMonth, $startOfLastMonth, $endOfLastMonth, $today
        ) {
            try {
                // 1. Total Revenue (amount_total of non-void transactions)
                $revenueThisMonth = Transaction::where('status', '!=', TransactionStatus::PENDING)
                    ->whereBetween('transaction_date', [$startOfThisMonth, $now])
                    ->sum('amount_total');
                $revenueLastMonth = Transaction::where('status', '!=', TransactionStatus::PENDING)
                    ->whereBetween('transaction_date', [$startOfLastMonth, $endOfLastMonth])
                    ->sum('amount_total');
                $revenueGrowth = $this->calculateGrowth($revenueThisMonth, $revenueLastMonth);

                // 2. New Customers
                $customersThisMonth = Customer::whereBetween('created_at', [$startOfThisMonth, $now])->count();
                $customersLastMonth = Customer::whereBetween('created_at', [$startOfLastMonth, $endOfLastMonth])->count();
                $customersGrowth = $this->calculateGrowth($customersThisMonth, $customersLastMonth);

                // 3. Total Sales (count of non-void transactions)
                $salesThisMonth = Transaction::where('status', '!=', TransactionStatus::PENDING)
                    ->whereBetween('transaction_date', [$startOfThisMonth, $now])
                    ->count();
                $salesLastMonth = Transaction::where('status', '!=', TransactionStatus::PENDING)
                    ->whereBetween('transaction_date', [$startOfLastMonth, $endOfLastMonth])
                    ->count();
                $salesGrowth = $this->calculateGrowth($salesThisMonth, $salesLastMonth);

                // 4. Pending Jobs
                $totalPending = Transaction::whereIn('status', ['pending', 'partial'])->count();
                $pendingAddedToday = Transaction::whereIn('status', ['pending', 'partial'])
                    ->where('created_at', '>=', $today)
                    ->count();

                return [
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
                    ],
                ];
            } catch (\Exception $e) {
                Log::error('DashboardController: stats query failed — ' . $e->getMessage());

                return [
                    'revenue'      => ['value' => 0.0, 'growth' => 0.0],
                    'customers'    => ['value' => 0,   'growth' => 0.0],
                    'sales'        => ['value' => 0,   'growth' => 0.0],
                    'pending_jobs' => ['value' => 0,   'added_today' => 0],
                ];
            }
        });

        // 5. Recent Transactions — cached for 2 minutes
        $recentTransactions = Cache::remember('dashboard.recent_transactions', 120, function () {
            try {
                return Transaction::with('customer')
                    ->latest('transaction_date')
                    ->take(5)
                    ->get()
                    ->map(fn ($t) => [
                        'id'               => $t->id,
                        'amount_total'     => (float) $t->amount_total,
                        'customer_name'    => $t->customer ? $t->customer->full_name : 'Walk-in',
                        'customer_company' => $t->customer && $t->customer->company ? $t->customer->company : 'N/A',
                    ]);
            } catch (\Exception $e) {
                Log::error('DashboardController: recent transactions query failed — ' . $e->getMessage());

                return [];
            }
        });

        // 6. Chart Data (Bar) — last 30 days, cached for 5 minutes
        $dailyData = Cache::remember('dashboard.chart_daily', 300, function () {
            try {
                return Transaction::select(
                    DB::raw("DATE_FORMAT(transaction_date, '%Y-%m-%d') as date"),
                    DB::raw('SUM(amount_total) as total'),
                    DB::raw('SUM(amount_paid) as paid')
                )
                    ->where('status', '!=', TransactionStatus::PENDING)
                    ->where('transaction_date', '>=', Carbon::now()->subDays(30))
                    ->groupBy('date')
                    ->orderBy('date')
                    ->get();
            } catch (\Exception $e) {
                Log::error('DashboardController: daily chart query failed — ' . $e->getMessage());

                return [];
            }
        });

        // 7. Chart Data (Pie) — last 6 months, limited to 1 000 rows before
        //    aggregation to cap memory/time on large tables, cached for 5 minutes
        $pieData = Cache::remember('dashboard.chart_pie', 300, function () {
            try {
                return Transaction::select(
                    DB::raw("DATE_FORMAT(transaction_date, '%M') as month"),
                    DB::raw("DATE_FORMAT(transaction_date, '%Y-%m') as sort_date"),
                    DB::raw('SUM(amount_total) as total')
                )
                    ->where('status', '!=', TransactionStatus::PENDING)
                    ->where('transaction_date', '>=', Carbon::now()->subMonths(6))
                    ->groupBy('month', 'sort_date')
                    ->orderBy('sort_date', 'asc')
                    ->limit(1000)
                    ->get()
                    ->map(fn ($item) => [
                        'month' => $item->month,
                        'total' => (float) $item->total,
                    ]);
            } catch (\Exception $e) {
                Log::error('DashboardController: pie chart query failed — ' . $e->getMessage());

                return [];
            }
        });

        return Inertia::render('dashboard/dashboard', [
            'stats'              => $stats,
            'recentTransactions' => $recentTransactions,
            'chartData'          => $dailyData,
            'pieData'            => $pieData,
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

