<?php

namespace App\Http\Controllers\Sales;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Services\Sales\SalesService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ReportController extends Controller
{
    public function __construct(protected SalesService $salesService) {}

    public function index(Request $request): Response
    {
        $filters = array_merge([
            'date' => now()->toDateString(),
            'mode' => 'daily',
        ], $request->only(['date', 'mode', 'branch_id']));

        $aggregates = $this->salesService->getPaymentAggregates($filters);
        $summary = $this->salesService->getFinanceSummary($filters);
        $gross = $this->salesService->getGrossRevenue($filters);

        return Inertia::render('reports/list', [
            'filters' => $filters,
            'branches' => Branch::accessibleBy(auth()->user())->get(['id', 'name']),
            'total_sales' => $aggregates['total_sales'],
            'gross_revenue' => $gross,
            'total_expenses' => $summary['total_expenses'],
            'net_income' => $summary['net_income'],
            'cash_amount' => $aggregates['cash_amount'],
            'gcash_amount' => $aggregates['gcash_amount'],
            'card_amount' => $aggregates['card_amount'],
            'check_amount' => $aggregates['check_amount'],
            'bank_transfer_amount' => $aggregates['bank_transfer_amount'],
            'debit_amount' => $aggregates['debit_amount'],
        ]);
    }
}
