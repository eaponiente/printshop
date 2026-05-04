<?php

namespace App\Http\Controllers\Sublimations;

use App\Enums\Sales\TransactionStatus;
use App\Enums\Sublimations\SublimationStatus;
use App\Enums\Users\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\StoreSublimationRequest;
use App\Http\Requests\Settings\UpdateSublimationRequest;
use App\Http\Requests\Sublimations\IndexSublimationRequest;
use App\Models\Branch;
use App\Models\Sublimation;
use App\Models\Tag;
use App\Models\Transaction;
use App\Models\User;
use App\Services\Sales\SalesService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class SublimationController extends Controller
{
    use AuthorizesRequests;

    public function __construct(protected SalesService $salesService) {}

    public function index(IndexSublimationRequest $request): Response
    {
        $query = Sublimation::with(['tags', 'branch', 'user', 'customer', 'transaction' => function ($query) {
            $query->withCount('payments');
        }]);

        $filters = $request->all();

        $specialBranches = ['Peñaplata', 'Babak', 'Tibungco'];

        $query->where(function ($query) use ($filters, $specialBranches) {
            $user = auth()->user()->load('branch');
            $filterIds = (array) ($filters['branch_id'] ?? []);
            $hasFilter = !empty($filterIds);

            // 1. SUPERADMIN: No restrictions unless filtering
            if ($user->role === UserRole::SUPERADMIN->value) {
                if ($hasFilter) {
                    $query->whereIn('branch_id', $filterIds);
                }
                return;
            }

            // 2. SPECIAL BRANCH GROUP (Babak, Peñaplata, Tibungco)
            if (in_array($user->branch->name, $specialBranches)) {
                $specialBranchesIds = Branch::whereIn('name', $specialBranches)->pluck('id')->toArray();

                if ($hasFilter) {

                    // Security: Only allow filtering within the special branches array
                    $validFilters = array_intersect($filterIds, $specialBranchesIds);
                    if (count($validFilters) === 0) {
                        return;
                    }
                    $query->whereIn('branch_id', $validFilters);
                } else {
                    // DEFAULT: Show all data from the 3 special branches
                    $query->whereIn('branch_id', $specialBranchesIds);
                }
                return;
            }

            if (in_array($user->branch_id, $specialBranches)) {
                $query->whereIn('branch_id', $specialBranches);
                return;
            }
            // 3. DEFAULT: Lock non-special users to their own branch
            $query->where('branch_id', $user->branch_id);
        });

        $query->when($request->filled('status') && $request->status !== 'all', fn($q) => $q->whereIn('status', $request->status));

        $query->when(
            ! $request->boolean('include_completed'),
            fn($q) => $q->where('status', '!=', 'completed'),
        );

        // handle unassigned
        $query->when($request->filled('user_id'), function ($q) use ($request) {
            if ($request->user_id === 'unassigned') {
                $q->whereNull('user_id');
            } else {
                $q->where('user_id', $request->user_id);
            }
        });

        $sortDirection = $request->query('sort_direction', 'desc');

        $query->orderBy($request->query('sort_field', 'due_at'), $sortDirection);

        if (auth()->user()->isSuperAdmin()) {
            $branches = Branch::get(['id', 'name']);
        } elseif (in_array(auth()->user()->branch?->name, $specialBranches)) {
            $branches = Branch::whereIn('name', $specialBranches)->get(['id', 'name']);
        } else {
            $branches = Branch::where('id', auth()->user()->branch_id)->get(['id', 'name']);
        }

        if (auth()->user()->role === 'superadmin') {
            $users = User::whereIn('role', ['admin', 'staff'])->get();
        } else {
            $users = User::whereIn('branch_id', $branches->pluck('id')->toArray())
                ->whereIn('role', ['admin', 'staff'])
                ->get();
        }



        return Inertia::render('sublimations/list', [
            'sublimations' => $query->paginate(30)->withQueryString(),
            'availableTags' => Tag::all(['id', 'name', 'color']),
            'filters' => $request->all(),
            'branches' => $branches,
            'users' => $users,
            'statuses' => SublimationStatus::map(),
        ]);
    }

    public function store(StoreSublimationRequest $request): RedirectResponse
    {
        try {
            $sublimation = Sublimation::query()->create($request->validated());

            return redirect()->back()->with('success', 'Sublimation created successfully.');
        } catch (\Exception $e) {
            Log::error('Failed to create sublimation: ' . $e->getMessage());

            return redirect()->back()->withErrors(['message' => 'An error occurred while creating the sublimation.']);
        }
    }

    public function update(UpdateSublimationRequest $request, Sublimation $sublimation): RedirectResponse
    {
        try {
            $sublimation->fill($request->validated());

            if ($sublimation->isDirty('amount_total')) {
                $hasTransaction = $sublimation->transaction()->exists();
                $transactionNotPending = $hasTransaction && $sublimation->transaction->status != TransactionStatus::PENDING->value;
                $inProduction = $sublimation->status->isProductionPhase();

                if ($transactionNotPending || $inProduction) {
                    return back()->withErrors(['message' => 'Cannot change amount—sublimation has been processed.']);
                }

                if ($hasTransaction) {
                    $sublimation->transaction->update(['amount_total' => $sublimation->amount_total]);
                }
            }

            $sublimation->save();

            return redirect()->back()->with('success', 'Sublimation updated successfully.');
        } catch (\Exception $e) {
            Log::error('Failed to update sublimation: ' . $e->getMessage());

            return redirect()->back()->withErrors(['message' => 'An error occurred while updating the sublimation.']);
        }
    }

    public function destroy(Sublimation $sublimation): RedirectResponse
    {
        try {
            if (! $sublimation->status->isPrePaymentPhase()) {
                return redirect()->back()->withErrors(['message' => 'You cannot delete this sublimation because it is not in the pre-payment phase.']);
            }

            if ($sublimation->transaction()->exists() && $sublimation->transaction->status != TransactionStatus::PENDING->value) {
                return redirect()->back()->withErrors(['message' => 'You cannot delete this sublimation because it has a transaction that is not in the pre-payment phase.']);
            }

            foreach ($sublimation->images as $image) {
                if (Storage::disk('s3')->exists($image->url)) {
                    Storage::disk('s3')->delete($image->url);
                }
            }


            $sublimation->transaction()->delete();
            $sublimation->delete();

            return redirect()->back()->with('success', 'Sublimation deleted successfully.');
        } catch (\Exception $e) {
            Log::error('Failed to delete sublimation: ' . $e->getMessage());

            return redirect()->back()->withErrors(['message' => 'An error occurred while deleting the sublimation.']);
        }
    }

    public function updateStatus(Request $request, Sublimation $sublimation): RedirectResponse
    {
        $newStatus = SublimationStatus::tryFrom($request->status);
        if (!$newStatus) {
            return back()->withErrors(['status' => 'Invalid status provided.']);
        }

        try {
            if (! $sublimation->canMoveTo($newStatus)) {
                return back()->withErrors([
                    'status' => "Cannot move to '{$newStatus->value}'. Please settle the downpayment or select 'Purchase Order' / 'Authorize Production' first.",
                ]);
            }

            DB::transaction(function () use ($sublimation, $newStatus, $request) {
                if ($newStatus === SublimationStatus::WAITING_FOR_DP) {
                    // Check if a transaction already exists to prevent duplicates
                    if (! $sublimation->transaction()->exists()) {
                        $transactionData = $sublimation->only(['description', 'branch_id', 'customer_id', 'user_id']);

                        $transaction = $this->salesService->createTransaction(array_merge($transactionData, [
                            'invoice_number' => Transaction::generateNumber(),
                            'amount_total' => $sublimation->amount_total,
                            'particular' => 'Sublimation',
                            'staff_id' => auth()->id(),
                            'transaction_date' => now()
                        ]));

                        $sublimation->transaction_id = $transaction->id;
                    }
                }

                $sublimation->status = $newStatus;
                $sublimation->save();
            });



            return back()->with('success', 'Status updated.');
        } catch (\Exception $e) {
            Log::error("Failed to update sublimation status #{$sublimation->id}: " . $e->getMessage());

            return back()->withErrors(['status' => 'The status change is not allowed at this time.']);
        }
    }

    public function updateStaff(Request $request, Sublimation $sublimation): RedirectResponse
    {
        $validated = $request->validate([
            'user_id' => 'nullable|exists:users,id',
        ]);

        try {
            $sublimation->update($validated);

            return back()->with('success', 'Staff updated successfully.');
        } catch (\Exception $e) {
            Log::error('Failed to update sublimation staff: ' . $e->getMessage());

            return back()->withErrors(['message' => 'An error occurred while updating the sublimation staff.']);
        }
    }

    public function updateDueDate(Request $request, Sublimation $sublimation): RedirectResponse
    {
        $validated = $request->validate([
            'due_at' => 'nullable|date|after:yesterday',
        ]);

        try {
            $sublimation->update($validated);

            return back()->with('success', 'Due date updated successfully.');
        } catch (\Exception $e) {
            Log::error('Failed to update sublimation due date: ' . $e->getMessage());

            return back()->withErrors(['message' => 'An error occurred while updating the sublimation due date.']);
        }
    }
}
