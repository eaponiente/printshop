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
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function index(Request $request): Response
    {
        $user = auth()->user();
        $cacheKey = "dashboard_data_user_{$user->id}";

        $data = Cache::remember($cacheKey, now()->addMinutes(5), function () use ($user) {
            $now = Carbon::now();
            $startOfMonth = $now->copy()->startOfMonth();
            $startOfLastMonth = $now->copy()->subMonth()->startOfMonth();
            $endOfLastMonth = $now->copy()->subMonth()->endOfMonth();

            // ------------------------------------------------------------------
            // Base query scope: restrict by branch for non-superadmins
            // ------------------------------------------------------------------
            $branchScope = function ($query) use ($user) {
                if ($user->isAdmin()) {
                    $query->where('branch_id', $user->branch_id);
                } elseif ($user->isStaff()) {
                    $query->where('staff_id', $user->id);
                }
            };

            // ------------------------------------------------------------------
            // 1. REVENUE STATS (sum of amount_paid on transactions)
            // ------------------------------------------------------------------
            $currentRevenue = Transaction::query()
                ->tap($branchScope)
                ->whereBetween('transaction_date', [$startOfMonth, $now])
                ->sum('amount_paid');

            $previousRevenue = Transaction::query()
                ->tap($branchScope)
                ->whereBetween('transaction_date', [$startOfLastMonth, $endOfLastMonth])
                ->sum('amount_paid');

            // ------------------------------------------------------------------
            // 2. CUSTOMER STATS (unique customers with transactions this month)
            // ------------------------------------------------------------------
            $currentCustomers = Customer::query()
                ->whereHas('transactions', function ($q) use ($user, $startOfMonth, $now) {
                    $q->whereBetween('transaction_date', [$startOfMonth, $now]);
                    if ($user->isAdmin()) {
                        $q->where('branch_id', $user->branch_id);
                    } elseif ($user->isStaff()) {
                        $q->where('staff_id', $user->id);
                    }
                })
                ->count();

            $previousCustomers = Customer::query()
                ->whereHas('transactions', function ($q) use ($user, $startOfLastMonth, $endOfLastMonth) {
                    $q->whereBetween('transaction_date', [$startOfLastMonth, $endOfLastMonth]);
                    if ($user->isAdmin()) {
                        $q->where('branch_id', $user->branch_id);
                    } elseif ($user->isStaff()) {
                        $q->where('staff_id', $user->id);
                    }
                })
                ->count();

            // ------------------------------------------------------------------
            // 3. SALES COUNT (total transactions this month)
            // ------------------------------------------------------------------
            $currentSales = Transaction::query()
                ->tap($branchScope)
                ->whereBetween('transaction_date', [$startOfMonth, $now])
                ->count();

            $previousSales = Transaction::query()
                ->tap($branchScope)
                ->whereBetween('transaction_date', [$startOfLastMonth, $endOfLastMonth])
                ->count();

            // ------------------------------------------------------------------
            // 4. PENDING JOBS (pending + partial transactions)
            // ------------------------------------------------------------------
            $currentPending = Transaction::query()
                ->tap($branchScope)
                ->whereIn('status', [TransactionStatus::PENDING->value, TransactionStatus::PARTIAL->value])
                ->whereBetween('transaction_date', [$startOfMonth, $now])
                ->count();

            $previousPending = Transaction::query()
                ->tap($branchScope)
                ->whereIn('status', [TransactionStatus::PENDING->value, TransactionStatus::PARTIAL->value])
                ->whereBetween('transaction_date', [$startOfLastMonth, $endOfLastMonth])
                ->count();

            // ------------------------------------------------------------------
            // 5. RECENT TRANSACTIONS (last 5)
            // ------------------------------------------------------------------
            $recentTransactions = Transaction::query()
                ->with(['customer', 'branch:id,name', 'user:id,first_name,last_name'])
                ->tap($branchScope)
                ->orderByDesc('transaction_date')
                ->limit(5)
                ->get();

            // ------------------------------------------------------------------
            // 6. DAILY BAR CHART DATA (last 30 days)
            // ------------------------------------------------------------------
            $thirtyDaysAgo = $now->copy()->subDays(29)->startOfDay();

            $dailyRaw = Transaction::query()
                ->tap($branchScope)
                ->whereBetween('transaction_date', [$thirtyDaysAgo, $now])
                ->select(
                    DB::raw('DATE(transaction_date) as date'),
                    DB::raw('SUM(amount_paid) as total')
                )
                ->groupBy(DB::raw('DATE(transaction_date)'))
                ->orderBy('date')
                ->pluck('total', 'date')
                ->toArray();

            // Fill in every day in the range (zero for days with no transactions)
            $chartData = [];
            for ($i = 29; $i >= 0; $i--) {
                $day = $now->copy()->subDays($i)->toDateString();
                $chartData[] = [
                    'date'  => $day,
                    'total' => (float) ($dailyRaw[$day] ?? 0),
                ];
            }

            // ------------------------------------------------------------------
            // 7. MONTHLY PIE CHART DATA (last 6 months)
            // ------------------------------------------------------------------
            $sixMonthsAgo = $now->copy()->subMonths(5)->startOfMonth();

            $monthlyRaw = Transaction::query()
                ->tap($branchScope)
                ->whereBetween('transaction_date', [$sixMonthsAgo, $now])
                ->select(
                    DB::raw('YEAR(transaction_date) as year'),
                    DB::raw('MONTH(transaction_date) as month'),
                    DB::raw('SUM(amount_paid) as total')
                )
                ->groupBy(DB::raw('YEAR(transaction_date)'), DB::raw('MONTH(transaction_date)'))
                ->orderBy('year')
                ->orderBy('month')
                ->get()
                ->keyBy(fn ($row) => "{$row->year}-{$row->month}")
                ->toArray();

            // Fill in every month in the range (zero for months with no transactions)
            $pieData = [];
            for ($i = 5; $i >= 0; $i--) {
                $month = $now->copy()->subMonths($i);
                $key = "{$month->year}-{$month->month}";
                $pieData[] = [
                    'month' => $month->format('M Y'),
                    'total' => (float) ($monthlyRaw[$key]['total'] ?? 0),
                ];
            }

            return [
                'stats' => [
                    'revenue' => [
                        'value'  => (float) $currentRevenue,
                        'growth' => $this->calculateGrowth($currentRevenue, $previousRevenue),
                    ],
                    'customers' => [
                        'value'  => $currentCustomers,
                        'growth' => $this->calculateGrowth($currentCustomers, $previousCustomers),
                    ],
                    'sales' => [
                        'value'  => $currentSales,
                        'growth' => $this->calculateGrowth($currentSales, $previousSales),
                    ],
                    'pendingJobs' => [
                        'value'  => $currentPending,
                        'growth' => $this->calculateGrowth($currentPending, $previousPending),
                    ],
                ],
                'recentTransactions' => $recentTransactions,
                'chartData'          => $chartData,
                'pieData'            => $pieData,
            ];
        });

        return Inertia::render('dashboard/dashboard', $data);
    }

    private function calculateGrowth($current, $previous): float
    {
        if ($previous == 0) {
            return $current > 0 ? 100 : 0;
        }

        return round((($current - $previous) / $previous) * 100, 1);
    }
}
