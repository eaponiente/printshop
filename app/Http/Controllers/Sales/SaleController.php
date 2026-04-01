<?php

namespace App\Http\Controllers\Sales;

use App\Enums\Shared\TypeOfPaymentEnum;
use App\Http\Controllers\Controller;
use App\Http\Requests\Transactions\GetTransactionsRequest;
use App\Http\Requests\Transactions\StoreTransactionRequest;
use App\Http\Requests\Transactions\UpdateTransactionPaymentRequest;
use App\Models\Branch;
use App\Models\CashOnHand;
use App\Models\Transaction;
use App\Services\Sales\CashOnHandService;
use App\Services\Sales\SalesService;
use Inertia\Inertia;

class SaleController extends Controller
{
    public function __construct(protected SalesService $salesService) {}

    public function index(GetTransactionsRequest $request)
    {
        $filters = array_merge([
            'date' => now()->toDateString(),
            'mode' => 'daily',
        ], $request->validated());

        $query = Transaction::query()
            ->with(['user:id,first_name,last_name', 'branch:id,name', 'customer'])
            ->filtered($filters)
            ->when($filters['status'] ?? null, fn ($q, $s) => $s !== 'all' ? $q->where('status', $s) : $q)
            ->when($filters['payment_type'] ?? null, fn ($q, $s) => $s !== 'all' ? $q->where('payment_type', $s) : $q)
            ->latest('transaction_date');

        $cashOnHandQuery = CashOnHand::query();
        $cashOnHandQuery->when($request->filled('branch_id') && $request->branch_id !== 'all', function ($q) use ($request) {
            $q->where('branch_id', $request->branch_id);
        });


        return Inertia::render('sales/list', [
            'filters' => $filters,
            'branches' => Branch::all(['id', 'name']),
            'customers' => $this->salesService->searchCustomers($filters['search'] ?? null),
            'transactions' => $query->paginate(30)->withQueryString(),
            'types_of_payment' => TypeOfPaymentEnum::map(),
            'total_sales' => (float) $query->sum('amount_paid'),
            'gcash_amount' => (float) (clone $query)->where('payment_type', 'gcash')->sum('amount_paid'),
            'card_amount' => (float) (clone $query)->where('payment_type', 'card')->sum('amount_paid'),
            'check_amount' => (float) (clone $query)->where('payment_type', 'check')->sum('amount_paid'),
            'bank_transfer_amount' => (float) (clone $query)->where('payment_type', 'bank_transfer')->sum('amount_paid'),
            'cash_amount' => (float) (clone $query)->where('payment_type', 'cash')->sum('amount_paid'),
            'cash_on_hand_amount' => (float) $cashOnHandQuery->sum('amount'),
            ...$this->salesService->getFinanceSummary($filters),
        ]);
    }

    public function store(StoreTransactionRequest $request)
    {
        Transaction::create(array_merge($request->validated(), [
            'staff_id' => auth()->id(),
            'transaction_date' => now(),
            'invoice_number' => Transaction::generateNumber(),
        ]));

        return back();
    }

    public function updatePayment(UpdateTransactionPaymentRequest $request, Transaction $transaction)
    {
        try {
            // Logic moved to a transition method on the Model (Encapsulation)
            $transaction->recordPayment($request->amount_paid, $request->payment_type);

            if ($transaction->payment_type === TypeOfPaymentEnum::CASH->value) {
                app(CashOnHandService::class)->adjustBalance(
                    $transaction->branch_id,
                    $request->amount_paid,
                    'revenue',
                    "Payment for Invoice #{$transaction->invoice_number}"
                );
            }


            return back()->with('success', 'Payment updated.');
        } catch (\Exception $e) {
            return back()->withErrors(['amount_paid' => $e->getMessage()]);
        }
    }
}
