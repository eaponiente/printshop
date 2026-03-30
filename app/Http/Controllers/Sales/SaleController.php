<?php

namespace App\Http\Controllers\Sales;

use App\Enums\Sales\TransactionStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\ProfileDeleteRequest;
use App\Http\Requests\Transactions\GetTransactionsRequest;
use App\Http\Requests\Transactions\StoreTransactionRequest;
use App\Http\Requests\Transactions\UpdateTransactionPaymentRequest;
use App\Http\Requests\Transactions\UpdateTransactionRequest;
use App\Models\Branch;
use App\Models\Customer;
use App\Models\Transaction;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class SaleController extends Controller
{
    public function index(GetTransactionsRequest $request)
    {
        // 1. Extract and Default Filters
        $filters = $request->validated();

        // Apply defaults: If no date is selected, use today's date in 'daily' mode
        $filters['date'] = $filters['date'] ?? now()->toDateString();
        $filters['mode'] = $filters['mode'] ?? 'daily';

        // 2. Build Base Transaction Query
        $query = Transaction::query()
            ->with([
                'user:id,first_name,last_name', // Short-hand eager loading
                'branch:id,name',
                'customer',
            ])
            ->tap(function ($q) use ($filters) {
                $this->applyDateFilter($q, $filters['date'], $filters['mode']);
            })
            ->when($request->filled('status') && $request->status !== 'all', fn ($q) => $q->where('status', $request->status)
            )
            ->when($request->filled('branch_id') && $request->branch_id !== 'all',
                fn ($q) => $q->where('branch_id', $request->branch_id)
            )
            ->when($filters['search'] ?? null, function ($q, $search) {
                $term = "%{$search}%";
                $q->where(function ($sub) use ($term) {
                    $sub->where('invoice_number', 'like', $term)
                        ->orWhereHas('customer', fn ($c) => $c->whereAny(['first_name', 'last_name'], 'like', $term)
                        );
                });
            });

        // 3. Customer Lookup (Separated logic)
        $customers = Customer::query()
            ->when($filters['customer'] ?? null, fn ($q, $search) => $q->whereAny(['first_name', 'last_name', 'company'], 'like', "%{$search}%")
            )
            ->limit(10)
            ->get();

        // 4. Calculate Totals (Clone before Sort/Pagination)
        $totalsQuery = clone $query;

        return Inertia::render('sales/list', [
            'filters' => $filters,
            'customers' => $customers,
            'branches' => Branch::all(['id', 'name']),
            'transactions' => $query->latest('transaction_date')->paginate(30)->withQueryString(),
            'total_sales' => (float) $totalsQuery->sum('amount_paid'),
            'total_balance' => (float) $totalsQuery->sum(DB::raw('amount_total - amount_paid')),
            'types_of_payment' => config('settings.type_of_payment'),
        ]);
    }

    /**
     * Extracted helper for cleaner date logic
     */
    private function applyDateFilter($query, $date, $mode)
    {
        match ($mode) {
            'daily' => $query->whereDate('transaction_date', $date),
            'weekly' => $query->whereRaw('YEARWEEK(transaction_date, 1) = ?', [str_replace('-W', '', $date)]),
            'monthly' => $query->whereMonth('transaction_date', Carbon::parse($date)->month)
                ->whereYear('transaction_date', Carbon::parse($date)->year),
            default => $query->whereDate('transaction_date', now()),
        };
    }

    public function store(StoreTransactionRequest $request)
    {
        $validated = $request->validated();

        $validated['staff_id'] = auth()->id();
        $validated['transaction_date'] = now();
        $validated['invoice_number'] = Transaction::generateNumber();

        Transaction::query()->create($validated);

        return redirect()->back();
    }

    /**
     * Update the user's profile information.
     */
    public function update(UpdateTransactionRequest $request, Transaction $transaction): RedirectResponse
    {
        $validated = $request->validated();

        $transaction->update($validated);

        return to_route('profile.edit');
    }

    /**
     * Delete the user's profile.
     */
    public function destroy(ProfileDeleteRequest $request): RedirectResponse
    {
        $user = $request->user();

        Auth::logout();

        $user->delete();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/');
    }

    public function updatePayment(UpdateTransactionPaymentRequest $request, Transaction $transaction) {

        if ($request->amount_paid == $transaction->amount_total) {
            $transaction->status = TransactionStatus::PAID;
            $transaction->save();
        }

        return redirect()->back();
    }
}
