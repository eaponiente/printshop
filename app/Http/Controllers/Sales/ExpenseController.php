<?php

namespace App\Http\Controllers\Sales;

use App\Enums\Expenses\ExpenseStatus;
use App\Enums\Expenses\ExpenseTypeOfPaymentEnum;
use App\Enums\Sales\TransactionStatus;
use App\Enums\Sales\TransactionTypeOfPaymentEnum;
use App\Enums\Users\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Sales\StoreExpenseRequest;
use App\Http\Requests\Sales\UpdateExpenseRequest;
use App\Models\Branch;
use App\Models\Expense;
use App\Models\Transaction;
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
        $user = auth()->user();
        if ($user->role === UserRole::STAFF->value) {
            abort(403);
        }

        $request->mergeIfMissing([
            'mode' => 'monthly',
        ]);

        $query = Expense::query()->with(['branch', 'user.branch'])
            ->dateFiltered($request->all())

            ->when($request->filled('branch_id') && $request->branch_id !== 'all', function ($sq) use ($request) {
                $sq->where('branch_id', $request->branch_id);
            })
            ->when($request->filled('payment_type'), function ($q) use ($request) {
                $q->where('payment_type', $request->payment_type);
            });

        $expenseAmount = clone $query;

        return Inertia::render('expenses/list', [
            'filters' => $request->all(),
            'expenses_amount' => $expenseAmount->where('status', ExpenseStatus::PAID->value)->sum('amount'),
            'expenses' => $query->latest('expense_date')->paginate(30)->withQueryString(),
            'branches' => Branch::get(['id', 'name']),
            'payment_methods' => ExpenseTypeOfPaymentEnum::map(),
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

                $isCreditCrossBranch = $validated['payment_type'] === ExpenseTypeOfPaymentEnum::CREDIT->value
                    && !empty($validated['creditor_branch_id'])
                    && !empty($validated['debtor_branch_id'])
                    && $validated['creditor_branch_id'] != $validated['debtor_branch_id'];
                $isRegularCrossBranch = $validated['payment_type'] !== ExpenseTypeOfPaymentEnum::CREDIT->value
                    && $validated['branch_id'] != auth()->user()->branch_id;
                $isSuperAdmin = auth()->user()->role === 'superadmin';
                $status = (($isCreditCrossBranch || $isRegularCrossBranch) && !$isSuperAdmin)
                    ? ExpenseStatus::PENDING->value
                    : ExpenseStatus::PAID->value;
                $validated['status'] = $status;

                $expense = Expense::create($validated);

                if ($expense->status === ExpenseStatus::PAID->value && $expense->payment_type === ExpenseTypeOfPaymentEnum::CASH->value) {
                    app(CashOnHandService::class)->adjustBalance(
                        $expense->branch_id,
                        $expense->amount,
                        'expense'
                    );
                }
            });

            return back()->with('success', 'Expense created successfully.');
        } catch (\Exception $e) {
            Log::error('Failed to create expense: ' . $e->getMessage());

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
            Log::error('Failed to update expense: ' . $e->getMessage());

            return back()->withErrors(['message' => 'An error occurred while updating the expense.']);
        }
    }

    public function destroy(Expense $expense, FileUploadService $fileUploadService): RedirectResponse
    {
        try {
            // 4. Reverse the cash impact if it was paid in cash
            if ($expense->payment_type === ExpenseTypeOfPaymentEnum::CASH->value) {
                app(CashOnHandService::class)->adjustBalance(
                    $expense->branch_id,
                    (float) $expense->amount, // Positive amount to "refund" the drawer
                    'revenue' // Label it clearly for the audit trail
                );
            }
            $expense->delete();

            return back()->with('success', 'Expense deleted successfully.');
        } catch (\Exception $e) {
            Log::error('Failed to delete expense: ' . $e->getMessage());

            return back()->withErrors(['message' => 'An error occurred while deleting the expense.']);
        }
    }

    public function void(Request $request, Expense $expense): RedirectResponse
    {
        //$this->authorize('void', $expense);

        // 1. Pre-emptive check
        if ($expense->status === ExpenseStatus::VOID->value) {
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
                if ($expense->payment_type === ExpenseTypeOfPaymentEnum::CASH->value) {
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
            Log::error("Failed to void expense #{$expense->id}: " . $e->getMessage());

            return back()->withErrors([
                'message' => 'Failed to void the expense. Please try again or contact support.',
            ]);
        }
    }

    public function approve(Expense $expense): RedirectResponse
    {
        $this->authorize('approve', $expense);

        if ($expense->status !== ExpenseStatus::PENDING->value) {
            return back()->withErrors(['message' => 'Only pending expenses can be approved.']);
        }

        try {
            DB::transaction(function () use ($expense) {
                $expense->update(['status' => ExpenseStatus::PAID->value]);

                if ($expense->payment_type === ExpenseTypeOfPaymentEnum::CASH->value) {
                    app(CashOnHandService::class)->adjustBalance(
                        $expense->branch_id,
                        $expense->amount,
                        'expense'
                    );
                }

                // Credit expense from a different branch: create a debit transaction
                // in the approver's branch to record the inter-branch transfer.
                if (
                    $expense->payment_type === ExpenseTypeOfPaymentEnum::CREDIT->value
                    && $expense->debtor_branch_id
                ) {
                    $transaction = Transaction::create([
                        'invoice_number' => Transaction::generateNumber(),
                        'customer_id' => null,
                        'particular' => 'Credit Expense — ' . ($expense->vendor_name ?: 'Expense #' . $expense->id),
                        'description' => $expense->description,
                        'amount_total' => $expense->amount,
                        'amount_paid' => 0,
                        'status' => TransactionStatus::PENDING->value,
                        'staff_id' => auth()->id(),
                        'branch_id' => $expense->debtor_branch_id,
                        'transaction_date' => now(),
                    ]);

                    $transaction->recordPayment(
                        $expense->amount,
                        TransactionTypeOfPaymentEnum::DEBIT->value
                    );
                }
            });

            return back()->with('success', 'Expense approved successfully.');
        } catch (\Exception $e) {
            Log::error("Failed to approve expense #{$expense->id}: " . $e->getMessage());
            return back()->withErrors(['message' => 'Failed to approve the expense.']);
        }
    }

    public function reject(Expense $expense): RedirectResponse
    {
        $this->authorize('reject', $expense);

        if ($expense->status !== ExpenseStatus::PENDING->value) {
            return back()->withErrors(['message' => 'Only pending expenses can be rejected.']);
        }

        try {
            $expense->update(['status' => ExpenseStatus::REJECTED->value]);
            return back()->with('success', 'Expense rejected successfully.');
        } catch (\Exception $e) {
            Log::error("Failed to reject expense #{$expense->id}: " . $e->getMessage());
            return back()->withErrors(['message' => 'Failed to reject the expense.']);
        }
    }
}
