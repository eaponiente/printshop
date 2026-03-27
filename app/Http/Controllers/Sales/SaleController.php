<?php

namespace App\Http\Controllers\Sales;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\ProfileDeleteRequest;
use App\Http\Requests\Transactions\StoreTransactionRequest;
use App\Http\Requests\Transactions\UpdateTransactionRequest;
use App\Models\Branch;
use App\Models\Customer;
use App\Models\Transaction;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class SaleController extends Controller
{
    /**
     * Show the user's profile settings page.
     */
    public function index(Request $request)
    {
        // Use $request->only() or $request->input() with defaults for cleaner access
        $filters = $request->only(['date', 'mode', 'status', 'search', 'branch_id', 'customer']);

        // Start query with eager loading
        $query = Transaction::query()
            ->with([
                'user' => fn ($q) => $q->select(['id', 'first_name', 'last_name']),
                'branch' => fn ($q) => $q->select(['id', 'name']),
                'customer',
            ]); // Only select needed columns

        $customersQuery = Customer::query();

        if (! empty($filters['customer'])) {
            $customersQuery->whereAny(
                ['first_name', 'last_name', 'company'],
                'like',
                '%'.$filters['customer'].'%'
            );
        }

        // 1. Date/Mode Filtering
        if (! empty($filters['date'])) {
            $date = $filters['date'];
            $mode = $filters['mode'] ?? 'daily';

            $query->where(function ($q) use ($date, $mode) {
                match ($mode) {
                    'daily' => $q->whereDate('transaction_date', $date),
                    'weekly' => $q->whereRaw('YEARWEEK(transaction_date, 1) = ?', [str_replace('-W', '', $date)]),
                    'monthly' => $q->whereMonth('transaction_date', Carbon::parse($date)->month)
                        ->whereYear('transaction_date', Carbon::parse($date)->year),
                    default => null
                };
            });
        }

        // 2. Status Filtering
        $query->when($request->filled('status') && $request->status !== 'all', function ($q) use ($request) {
            $q->where('status', $request->status);
        });

        // Branch Filter: Only apply if it's not 'all' and not empty
        $query->when($request->filled('branch_id') && $request->branch_id !== 'all', function ($q) use ($request) {
            $q->where('branch_id', $request->branch_id);
        });

        $query->when($filters['search'] ?? null, function ($query, $search) {
            $searchTerm = "%{$search}%";

            $query->where(function ($q) use ($searchTerm) {
                $q->where('invoice_number', 'like', $searchTerm)
                    ->orWhereHas('customer', function ($childQ) use ($searchTerm) {
                        $childQ->whereAny(
                            ['first_name', 'last_name'],
                            'like',
                            $searchTerm
                        );
                    });
            });
        });

        $query->sort($request, ['transaction_date']);

        // Clone the query for totals before pagination & ordering
        $totalsQuery = clone $query;

        return Inertia::render('sales/list', [
            'customers' => $customersQuery->limit(10)->get(),
            'transactions' => $query->latest('transaction_date')->paginate(30)->withQueryString(),
            'filters' => $request->all(),
            'branches' => Branch::all(['id', 'name']), // Avoid selecting all columns if not needed
            'total_sales' => (float) $totalsQuery->sum('amount_paid'),
            'total_balance' => (float) $totalsQuery
                ->sum(DB::raw('amount_total - amount_paid')),
            'types_of_payment' => config()->get('settings.type_of_payment'),
        ]);
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
}
