<?php

namespace App\Http\Controllers\Sales;

use App\Enums\Sales\TransactionStatus;
use App\Enums\Sales\TransactionTypeOfPaymentEnum;
use App\Enums\Users\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Transactions\GetTransactionsRequest;
use App\Http\Requests\Transactions\RefundTransactionPaymentRequest;
use App\Http\Requests\Transactions\StoreTransactionRequest;
use App\Http\Requests\Transactions\UpdateTransactionPaymentRequest;
use App\Http\Requests\Transactions\UpdateTransactionRequest;
use App\Models\Branch;
use App\Models\Transaction;
use App\Models\User;
use App\Services\Files\FileUploadService;
use App\Services\Sales\CashOnHandService;
use App\Services\Sales\SalesService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;
use Payroll\Audit\Traits\Auditable;

class SaleController extends Controller
{
    use Auditable;

    public function __construct(protected SalesService $salesService) {}

    public function index(GetTransactionsRequest $request): Response
    {
        $filters = array_merge([
            'date' => now()->toDateString(),
            'mode' => 'daily',
            'tab' => 'partial',
        ], $request->validated());

        $tab = $filters['tab'] ?? 'partial';

        // The breakdown is fixed to Partial+Paid collections and shown on every
        // tab except Unpaid; it follows the date/branch/staff filters, not status.
        $showSummary = $tab !== 'unpaid';

        $cashOnHand = $this->salesService->getCashOnHandTotal($request->input('branch_id', auth()->user()->branch_id));

        $branches = Branch::query()
            ->when(auth()->user()->isStaff() || auth()->user()->isAdmin(), fn ($q) => $q->where('id', auth()->user()->branch_id))
            ->get(['id', 'name']);

        $users = auth()->user()->isSuperAdmin() || auth()->user()->isAdmin()
            ? User::whereIn('role', ['admin', 'staff'])->select('id', 'first_name', 'last_name', 'branch_id')->orderBy('first_name')->get()
            : collect();

        if ($tab === 'unpaid') {
            // Unpaid = pending transactions; they have no payments to list.
            $transactions = $this->salesService
                ->getTransactionQuery(array_merge($filters, ['status' => 'pending']))
                ->paginate(100)
                ->withQueryString();
            $isPaymentView = false;
        } else {
            // Partial / Paid: one row per payment (not grouped by transaction),
            // scoped to that settlement status of the parent transaction.
            $status = $tab === 'paid' ? 'paid' : 'partial';
            $transactions = $this->salesService
                ->getPaymentQuery(array_merge($filters, ['status' => $status]))
                ->paginate(100)
                ->withQueryString();
            $isPaymentView = true;
        }

        $summary = [];

        if ($showSummary) {
            // Deliberately pass $filters WITHOUT a status key so the breakdown
            // always covers partial+paid (getPaymentQuery only sees paid-into
            // transactions, i.e. amount_paid > 0).
            $paymentQuery = $this->salesService->getPaymentQuery($filters);
            $summary = array_merge(
                $this->salesService->getPaymentAggregatesFromPayments($paymentQuery, $filters),
                $this->salesService->getFinanceSummaryFromPayments($paymentQuery, $filters),
            );
        }

        return Inertia::render('sales/list', array_merge([
            'filters' => $filters,
            'branches' => $branches,
            'users' => $users,
            'transactions' => $transactions,
            'types_of_payment' => TransactionTypeOfPaymentEnum::map(),
            'cash_on_hand_amount' => $cashOnHand,
            'is_payment_view' => $isPaymentView,
            'show_summary' => $showSummary,
        ], $summary));
    }

    public function print(Request $request): JsonResponse
    {
        $filters = array_merge([
            'date' => now()->toDateString(),
            'mode' => 'daily',
            'tab' => 'partial',
        ], $request->all());

        $paymentQuery = $this->salesService->getPaymentQuery($filters);
        $records = $paymentQuery->get();

        $headers = ['Customer Name', 'Particular', 'Branch', 'Total', 'Payment', 'Type', 'Balance', 'Status', 'Staff', 'Date'];
        $rows = $records->map(function ($payment) {
            $tx = $payment->transaction;

            return [
                $tx?->customer ? ($tx->customer->first_name.' '.($tx->customer->last_name ?? '')) : '',
                $tx?->particular ?? '',
                $tx?->branch?->name ?? '',
                number_format($tx?->amount_total ?? 0, 2),
                number_format($payment->amount, 2),
                ucfirst($payment->payment_type),
                number_format($tx?->balance ?? 0, 2),
                ucfirst($tx?->status ?? ''),
                $tx?->user ? ($tx->user->first_name.' '.$tx->user->last_name) : '',
                Carbon::parse($payment->created_at)->setTimezone('Asia/Manila')->format('M d, Y'),
            ];
        })->values()->toArray();

        return response()->json(compact('headers', 'rows'));
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

            return back()->withErrors(['message' => 'An error occurred while creating the sale.']);
        }
    }

    public function update(UpdateTransactionRequest $request, Transaction $sale)
    {
        // 1. Fill the model with the validated data (in-memory only)
        $sale->fill($request->validated());

        // 2. Check if 'amount_total' was actually changed to a new value
        if ($sale->isDirty('amount_total')) {

            // Custom logic: e.g., only allow change if status is pending
            if ($sale->getOriginal('status') !== TransactionStatus::PENDING->value) {
                return back()->withErrors(['message' => 'Cannot change amount on processed sales.']);
            }

            // If you we amount total here we also update the sublimation amount total
            if ($sale->sublimation()->exists()) {
                $sale->sublimation->update([
                    'amount_total' => $sale->amount_total,
                ]);
            }
        }

        $sale->save();

        return back()->with('success', 'Sale updated successfully.');
    }

    public function updatePayment(UpdateTransactionPaymentRequest $request, Transaction $transaction): RedirectResponse
    {
        try {
            // Logic moved to a transition method on the Model (Encapsulation)
            $transaction->recordPayment($request->amount_paid, $request->payment_type);

            if ($request->payment_type === TransactionTypeOfPaymentEnum::CASH->value) {
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

    public function refundPayment(RefundTransactionPaymentRequest $request, Transaction $transaction): RedirectResponse
    {
        try {
            DB::transaction(function () use ($transaction) {
                $before = [
                    'status' => $transaction->status,
                    'amount_paid' => $transaction->amount_paid,
                ];

                $cashRefund = $transaction->payments()
                    ->live()
                    ->where('payment_type', TransactionTypeOfPaymentEnum::CASH->value)
                    ->sum('amount');

                $refundedTotal = $transaction->refundPayment();

                if ($cashRefund > 0) {
                    app(CashOnHandService::class)->adjustBalance(
                        $transaction->branch_id,
                        (float) $cashRefund,
                        'expense'
                    );
                }

                $transaction->refresh();

                $this->audit('refunded', $transaction, $before, [
                    'status' => $transaction->status,
                    'amount_paid' => $transaction->amount_paid,
                    'refunded_total' => $refundedTotal,
                    'cash_refunded' => (float) $cashRefund,
                ]);
            });

            return back()->with('success', 'Full refund recorded.');
        } catch (\Exception $e) {
            Log::error('Failed to refund payment: '.$e->getMessage());

            return back()->withErrors(['payment_type' => $e->getMessage()]);
        }
    }

    public function storeAttachment(Request $request, Transaction $sale, FileUploadService $fileUploadService): RedirectResponse
    {
        $this->authorizeTransactionAccess($sale);

        $request->validate([
            'attachment' => ['required', 'image', 'max:5120'],
        ]);

        try {
            if ($sale->attachment_path) {
                $fileUploadService->delete($sale->attachment_path);
            }

            $path = $fileUploadService->upload($request->file('attachment'), 'sale_attachments');
            $sale->update(['attachment_path' => $path]);

            return back()->with('success', 'Attachment saved.');
        } catch (\Exception $e) {
            Log::error('Failed to upload sale attachment: '.$e->getMessage());

            return back()->withErrors(['attachment' => 'Could not upload attachment.']);
        }
    }

    public function destroyAttachment(Transaction $sale, FileUploadService $fileUploadService): RedirectResponse
    {
        $this->authorizeTransactionAccess($sale);

        if (! $sale->attachment_path) {
            return back()->withErrors(['attachment' => 'No attachment to remove.']);
        }

        try {
            $fileUploadService->delete($sale->attachment_path);
            $sale->update(['attachment_path' => null]);

            return back()->with('success', 'Attachment removed.');
        } catch (\Exception $e) {
            Log::error('Failed to delete sale attachment: '.$e->getMessage());

            return back()->withErrors(['attachment' => 'Could not remove attachment.']);
        }
    }

    public function destroy(Transaction $sale): RedirectResponse
    {
        // only superadmin
        if (! auth()->user()->isSuperAdmin()) {
            return back()->withErrors(['message' => 'Only superadmin can delete sales.']);
        }

        // check if transaction exists
        if ($sale->deleted_at !== null) {
            return back()->withErrors(['message' => 'Transaction already deleted.']);
        }

        // only if pending
        if ($sale->status !== TransactionStatus::PENDING->value) {
            return back()->withErrors(['message' => 'Cannot delete processed sales.']);
        }

        // only if no payments (allow refunded transactions where amount_paid netted to 0)
        if ($sale->amount_paid > 0) {
            return back()->withErrors(['message' => 'Cannot delete sales with payments.']);
        }

        try {
            $sale->delete();

            return back()->with('success', 'Sale deleted successfully.');
        } catch (\Exception $e) {
            Log::error('Failed to delete sale: '.$e->getMessage());

            return back()->withErrors(['message' => 'An error occurred while deleting the sale.']);
        }
    }

    private function authorizeTransactionAccess(Transaction $transaction): void
    {
        $user = auth()->user();

        // super admin can delete
        if ($user->isSuperAdmin()) {
            return;
        }

        // must have the same branch
        if ((int) $user->branch_id !== (int) $transaction->branch_id) {
            abort(403);
        }

        // staff can only access their own transactions
        if ($user->role === UserRole::STAFF->value && (int) $transaction->staff_id !== (int) $user->id) {
            abort(403);
        }
    }
}
