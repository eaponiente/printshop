<?php

namespace App\Http\Controllers\Sales;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\Expense;
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
        $query = Expense::query()->with(['user', 'branch']);

        return Inertia::render('expenses/list', [
            'expenses' => $query->paginate(30)->withQueryString(),
            'branches' => Branch::all(),
            'payment_methods' => config()->get('settings.type_of_payment'),
        ]);
    }

    public function store(Request $request)
    {
        $typeOfPayments = collect(config('settings.type_of_payment'))->pluck('key')->toArray();

        $validated = $request->validate([
            'description' => 'required|string|max:1000',
            'vendor_name' => 'nullable|string|max:255',
            'amount' => 'required|numeric|min:0',
            'payment_method' => ['nullable', Rule::in($typeOfPayments)],
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

        Expense::create($validated);

        // Inertia expects a redirect back to the index or current page
        return back()->with('success', 'Expense created successfully.');
    }

    /**
     * Update the specified resource.
     */
    public function update(Request $request, Expense $expense)
    {
        $typeOfPayments = collect(config('settings.type_of_payment'))->pluck('key')->toArray();

        $validated = $request->validate([
            'description' => 'required|string|max:1000',
            'vendor_name' => 'nullable|string|max:255',
            'amount' => 'required|numeric|min:0',
            'payment_method' => ['nullable', Rule::in($typeOfPayments)],
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
