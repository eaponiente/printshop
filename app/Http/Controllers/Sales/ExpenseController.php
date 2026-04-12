<?php

namespace App\Http\Controllers\Sales;

use App\Enums\Expenses\ExpenseStatus;
use App\Enums\Shared\TypeOfPaymentEnum;
use App\Http\Controllers\Controller;
use App\Http\Requests\Sales\StoreExpenseRequest;
use App\Http\Requests\Sales\UpdateExpenseRequest;
use App\Models\Branch;
use App\Models\Expense;
use App\Services\Files\FileUploadService;
use App\Services\Sales\CashOnHandService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;

class ExpenseController extends Controller
{
    use AuthorizesRequests;

    public function index(Request $request): Response
    {
        $query = Expense::query()->with(['user', 'branch'])
            ->filtered($request->all())
            ->when($request->filled('payment_type'), function ($q) use ($request) {
                $q->where('payment_type', $request->payment_type);
            })
            ->latest('expense_date');

        return Inertia::render('expenses/list', [
            'filters' => $request->all(),
            'expenses_amount' => $query->sum('amount'),
            'expenses' => $query->paginate(30)->withQueryString(),
            'branches' => Branch::accessibleBy(auth()->user())->get(['id', 'name']),
            'payment_methods' => TypeOfPaymentEnum::map(),
        ]);
    }

    public function store(StoreExpenseRequest $request, FileUploadService $fileUploadService): RedirectResponse
    {
        try {
            DB::transaction(function () use ($request, $fileUploadService) {
                $validated = $request->validated();

                if ($request->hasFile('receipt')) {
                    $validated['receipt'] = $fileUploadService->upload($request->file('receipt'), 'receipts');
                }

                $validated['user_id'] = auth()->id();
                $validated['expense_date'] = now();

                $expense = Expense::create($validated);

                if ($expense->payment_type === TypeOfPaymentEnum::CASH->value) {
                    app(CashOnHandService::class)->adjustBalance(
                        $expense->branch_id,
                        $expense->amount,
                        'expense'
                    );
                }
            });

            return back()->with('success', 'Expense created successfully.');
        } catch (\Exception $e) {
            Log::error('Failed to create expense: '.$e->getMessage());

            return back()->withErrors(['message' => 'An error occurred while creating the expense.']);
        }
    }

    /**
     * Update the specified resource.
     */
    public function update(UpdateExpenseRequest $request, Expense $expense, FileUploadService $fileUploadService): RedirectResponse
    {
        try {
            $validated = $request->validated();

            if ($request->hasFile('receipt')) {
                $fileUploadService->delete($expense->receipt);
                $validated['receipt'] = $fileUploadService->upload($request->file('receipt'), 'receipts');
            }

            $expense->update($validated);

            return back()->with('success', 'Expense updated successfully.');
        } catch (\Exception $e) {
            Log::error('Failed to update expense: '.$e->getMessage());

            return back()->withErrors(['message' => 'An error occurred while updating the expense.']);
        }
    }

    public function destroy(Expense $expense, FileUploadService $fileUploadService): RedirectResponse
    {
        $this->authorize('delete', auth()->user());

        try {
            $fileUploadService->delete($expense->receipt);
            $expense->delete();

            return back()->with('success', 'Expense deleted successfully.');
        } catch (\Exception $e) {
            Log::error('Failed to delete expense: '.$e->getMessage());

            return back()->withErrors(['message' => 'An error occurred while deleting the expense.']);
        }
    }

    public function void(Request $request, Expense $expense): RedirectResponse
    {
        // 1. Pre-emptive check
        if ($expense->status === 'void') {
            return back()->withErrors(['message' => 'Expense is already voided.']);
        }

        // 2. Validation
        $request->validate([
            'reason' => 'required|string|min:2',
        ]);

        try {
            DB::transaction(function () use ($expense, $request) {
                // 3. Mark as void and save the reason
                $expense->update([
                    'status' => ExpenseStatus::VOID->value,
                    'void_reason' => $request->reason,
                ]);

                // 4. Reverse the cash impact if it was paid in cash
                if ($expense->payment_type === TypeOfPaymentEnum::CASH->value) {
                    app(CashOnHandService::class)->adjustBalance(
                        $expense->branch_id,
                        (float) $expense->amount, // Positive amount to "refund" the drawer
                        'revenue' // Label it clearly for the audit trail
                    );
                }
            });

            return back()->with('success', 'Expense has been voided and cash balance adjusted.');

        } catch (\Exception $e) {
            // Log the error for debugging
            Log::error("Failed to void expense #{$expense->id}: ".$e->getMessage());

            return back()->withErrors([
                'message' => 'Failed to void the expense. Please try again or contact support.',
            ]);
        }
    }
}
