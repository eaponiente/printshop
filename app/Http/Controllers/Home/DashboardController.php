<?php

namespace App\Http\Controllers\Home;

use App\Http\Controllers\Controller;
use App\Models\Branch;
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
        $branches = Branch::all();

        $dailyData = Transaction::select(
            DB::raw("DATE_FORMAT(transaction_date, '%Y-%m-%d') as date"),
            DB::raw('SUM(amount_total) as total'),
            DB::raw('SUM(amount_paid) as paid')
        )
            ->where('status', '!=', 'void')
            ->where('transaction_date', '>=', Carbon::now()->subDays(30))
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        $pieData = Transaction::select(
            // We get the full month name for the label
            DB::raw("DATE_FORMAT(transaction_date, '%M') as month"),
            // We get a sortable date to ensure Jan comes before Feb
            DB::raw("DATE_FORMAT(transaction_date, '%Y-%m') as sort_date"),
            DB::raw('SUM(amount_total) as total')
        )
            ->where('status', '!=', 'void')
            ->where('transaction_date', '>=', Carbon::now()->subMonths(6))
            ->groupBy('month', 'sort_date')
            ->orderBy('sort_date', 'asc')
            ->get()
            ->map(fn ($item) => [
                'month' => $item->month,
                'total' => (float) $item->total,
            ]);

        return Inertia::render('dashboard/dashboard', [
            'chartData' => $dailyData,
            'pieData' => $pieData,
        ]);
    }
}
