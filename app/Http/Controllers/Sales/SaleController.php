<?php

namespace App\Http\Controllers\Sales;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\ProfileDeleteRequest;
use App\Http\Requests\Settings\ProfileUpdateRequest;
use App\Models\Branch;
use App\Models\Customer;
use App\Models\Transaction;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
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
        $query = Transaction::query()->with('user', 'branch'); // Only select needed columns

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

        // 3. Search Filtering (Grouped to prevent breaking other filters)
        $query->when($filters['search'] ?? null, function ($q, $search) {
            $q->where(function ($sub) use ($search) {
                $sub->where('invoice_number', 'like', "%{$search}%")
                    ->orWhere('guest_name', 'like', "%{$search}%");
            });
        });

        // Clone the query for totals before pagination & ordering
        $totalsQuery = clone $query;

        return Inertia::render('sales/list', [
            'customers' => $customersQuery->limit(10)->get(),
            'transactions' => $query->latest('transaction_date')->paginate(30)->withQueryString(),
            'filters' => $filters,
            'branches' => Branch::all(['id', 'name']), // Avoid selecting all columns if not needed
            'total_sales' => (float) $totalsQuery->sum('amount_paid'),
            'total_balance' => (float) $totalsQuery->sum('balance'),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'customer_id' => [
                'required',
                'string',
                'max:255',
                'exists:customers,id',
            ],
            'description' => [
                'required',
                'string',
                'max:255',
            ],
            'particular' => [
                'required',
                'string',
                'max:255',
            ],
        ], [
            'customer_id.required' => 'Customer field is required.',
        ]);

        return redirect()->back();
    }

    /**
     * Update the user's profile information.
     */
    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        $request->user()->fill($request->validated());

        if ($request->user()->isDirty('email')) {
            $request->user()->email_verified_at = null;
        }

        $request->user()->save();

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
