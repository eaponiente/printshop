<?php

namespace App\Http\Controllers\Sales;

use App\Enums\Shared\TypeOfPaymentEnum;
use App\Http\Controllers\Controller;
use App\Http\Requests\Transactions\GetTransactionsRequest;
use App\Http\Requests\Transactions\StoreTransactionRequest;
use App\Http\Requests\Transactions\UpdateTransactionPaymentRequest;
use App\Models\Branch;
use App\Models\Transaction;
use App\Services\Sales\CashOnHandService;
use App\Services\Sales\SalesService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;

class SaleController extends Controller
{
    public function __construct(protected SalesService $salesService) {}

    public function index(GetTransactionsRequest $request): Response
    {
        $filters = array_merge([
            'date' => now()->toDateString(),
            'mode' => 'daily',
        ], $request->validated());

        $query = $this->salesService->getTransactionQuery($filters);

        $aggregates = $this->salesService->getPaymentAggregates($query);
        $cashOnHand = $this->salesService->getCashOnHandTotal($request->input('branch_id'));

        return Inertia::render('sales/list', array_merge([
            'filters' => $filters,
            'branches' => Branch::accessibleBy(auth()->user())->get(['id', 'name']),
            'customers' => $this->salesService->searchCustomers($filters['search'] ?? null),
            'transactions' => $query->paginate(30)->withQueryString(),
            'types_of_payment' => TypeOfPaymentEnum::map(),
            'cash_on_hand_amount' => $cashOnHand,
        ], $aggregates, $this->salesService->getFinanceSummary($filters)));
    }

    public function store(StoreTransactionRequest $request): RedirectResponse
    {
        try {
            Transaction::create(array_merge($request->validated(), [
                'staff_id' => auth()->id(),
                'transaction_date' => now(),
                'invoice_number' => Transaction::generateNumber(),
            ]));

            return back()->with('success', 'Sale created successfully.');
        } catch (\Exception $e) {
            Log::error('Failed to create sale: '.$e->getMessage());

            return back()->withErrors(['error' => 'An error occurred while creating the sale.']);
        }
    }

    public function updatePayment(UpdateTransactionPaymentRequest $request, Transaction $transaction): RedirectResponse
    {
        try {
            // Logic moved to a transition method on the Model (Encapsulation)
            $transaction->recordPayment($request->amount_paid, $request->payment_type);

            if ($request->payment_type === TypeOfPaymentEnum::CASH->value) {
                app(CashOnHandService::class)->adjustBalance(
                    $transaction->branch_id,
                    $request->amount_paid,
                    'revenue'
                );
            }

            return back()->with('success', 'Payment updated.');
        } catch (\Exception $e) {
            Log::error('Failed to update payment: '.$e->getMessage());

            return back()->withErrors(['amount_paid' => $e->getMessage()]);
        }
    }
}
