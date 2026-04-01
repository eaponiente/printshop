<?php

namespace App\Http\Controllers\Sales;

use App\Enums\Shared\TypeOfPaymentEnum;
use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\Expense;
use App\Services\Sales\CashOnHandService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class ExpenseController extends Controller
{
    use AuthorizesRequests;

    public function index(Request $request): Response
    {
        $query = Expense::query()->with(['user', 'branch'])
            ->when($request->filled('branch_id'), function ($q) use ($request) {
                $q->where('branch_id', $request->branch_id);
            })
            ->when($request->filled('payment_type'), function ($q) use ($request) {
                $q->where('payment_type', $request->payment_type);
            })
            ->latest('expense_date');

        return Inertia::render('expenses/list', [
            'filters' => $request->all(),
            'expenses_amount' => $query->sum('amount'),
            'expenses' => $query->paginate(30)->withQueryString(),
            'branches' => Branch::all(),
            'payment_methods' => TypeOfPaymentEnum::map(),
        ]);
    }

    public function store(Request $request)
    {
        $typeOfPayments = TypeOfPaymentEnum::cases();

        $validated = $request->validate([
            'description' => 'required|string|max:1000',
            'vendor_name' => 'nullable|string|max:255',
            'amount' => 'required|numeric|min:0',
            'payment_type' => ['nullable', Rule::in($typeOfPayments)],
            'branch_id' => 'required|exists:branches,id',
            'expense_date' => 'required|date',
            'receipt' => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:2048',
        ]);

        if ($request->hasFile('receipt')) {
            // Store file and save the path
            $validated['receipt'] = $request->file('receipt')->store('receipts', 'public');
        }

        $validated['user_id'] = auth()->id();
        $validated['expense_date'] = now();

        $expense = Expense::create($validated);

        if ($expense->payment_type === TypeOfPaymentEnum::CASH->value) {
            // Decrease the Cash on Hand
            app(CashOnHandService::class)->adjustBalance(
                $expense->branch_id,
                $expense->amount,
                'expense',
                "Expense: {$expense->description}"
            );
        }


        // Inertia expects a redirect back to the index or current page
        return back()->with('success', 'Expense created successfully.');
    }

    /**
     * Update the specified resource.
     */
    public function update(Request $request, Expense $expense)
    {
        $typeOfPayments = TypeOfPaymentEnum::cases();

        $validated = $request->validate([
            'description' => 'required|string|max:1000',
            'vendor_name' => 'nullable|string|max:255',
            'amount' => 'required|numeric|min:0',
            'payment_type' => ['nullable', Rule::in($typeOfPayments)],
            'status' => 'required|in:pending,approved,rejected,reimbursed',
            'expense_date' => 'required|date',
            'receipt' => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:2048',
        ]);

        if ($request->hasFile('receipt')) {
            // Delete old file if it exists
            if ($expense->receipt) {
                Storage::disk('public')->delete($expense->receipt);
            }
            $validated['receipt'] = $request->file('receipt')->store('receipts', 'public');
        }

        $expense->update($validated);

        return back()->with('success', 'Expense updated successfully.');
    }

    public function destroy(Expense $expense)
    {
        $this->authorize('delete', auth()->user());

        $expense->delete();

        return back()->with('success', 'Expense deleted successfully.');
    }
}
