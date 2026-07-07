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
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Payroll\Employee\Enums\EmployeeStatus;

class SublimationController extends Controller
{
    use AuthorizesRequests;

    public function __construct(protected SalesService $salesService) {}

    public function index(IndexSublimationRequest $request): Response
    {
        $query = Sublimation::with(['tags', 'branch', 'user', 'customer', 'sewedItem', 'transaction' => function ($query) {
            $query->withCount('payments');
        }]);

        // Staff default to seeing only their own assigned sublimations unless they pick another filter.
        if (auth()->user()->role === UserRole::STAFF->value && ! $request->has('user_id')) {
            $request->merge(['user_id' => (string) auth()->id()]);
        }

        $filters = $request->all();

        $specialBranches = ['Peñaplata', 'Babak', 'Tibungco'];

        $query->where(function ($query) use ($filters, $specialBranches) {
            $user = auth()->user()->load('branch');
            $filterIds = (array) ($filters['branch_id'] ?? []);
            $hasFilter = ! empty($filterIds);

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
                    $validFilters = array_intersect($filterIds, $specialBranchesIds);
                    if (count($validFilters) === 0) {
                        return;
                    }
                    $query->whereIn('branch_id', $validFilters);
                } else {
                    $query->whereIn('branch_id', $specialBranchesIds);
                }

                return;
            }

            // 3. DEFAULT: Lock non-special users to their own branch
            $query->where('branch_id', $user->branch_id);
        });

        $query->when($request->filled('status') && $request->status !== 'all',
            fn ($q) => $q->whereIn('status', (array) $request->status)
        );

        $query->when(
            ! $request->boolean('include_completed'),
            fn ($q) => $q->where('status', '!=', 'completed'),
        );

        // handle unassigned
        $query->when($request->filled('user_id') && $request->user_id !== 'all', function ($q) use ($request) {
            if ($request->user_id === 'unassigned') {
                $q->whereNull('user_id');
            } else {
                $q->where('user_id', $request->user_id);
            }
        });

        // Backend-only filter by a specific sublimation id (used by deep links).
        $query->when($request->filled('id'), fn ($q) => $q->where('id', $request->integer('id')));

        // Free-text search across the sublimation description and customer name.
        $query->when($request->filled('search'), function ($q) use ($request) {
            $search = $request->string('search');

            $q->where(function ($sub) use ($search) {
                $sub->where('description', 'like', "%{$search}%")
                    ->orWhereHas('customer', function ($c) use ($search) {
                        $c->where('first_name', 'like', "%{$search}%")
                            ->orWhere('last_name', 'like', "%{$search}%");
                    });
            });
        });

        $sortDirection = in_array($request->query('sort_direction'), ['asc', 'desc'])
            ? $request->query('sort_direction')
            : 'desc';

        $sortField = in_array($request->query('sort_field'), ['due_at', 'created_at', 'user_id'])
            ? $request->query('sort_field')
            : 'created_at';

        $query->orderBy($sortField, $sortDirection);

        if (auth()->user()->isSuperAdmin()) {
            $branches = Branch::get(['id', 'name']);
        } elseif (in_array(auth()->user()->branch?->name, $specialBranches)) {
            $branches = Branch::whereIn('name', $specialBranches)->get(['id', 'name']);
        } else {
            $branches = Branch::where('id', auth()->user()->branch_id)->get(['id', 'name']);
        }

        $usersQuery = User::whereIn('role', ['admin', 'staff'])
            ->where(function ($query) {
                $query->whereNull('employee_id')
                    ->orWhereHas('employee', fn ($q) => $q->where('status', EmployeeStatus::ACTIVE->value));
            })
            ->orderBy('first_name');

        // Non-superadmins only see users within their accessible branches.
        if (auth()->user()->role !== 'superadmin') {
            $usersQuery->whereIn('branch_id', $branches->pluck('id')->toArray());
        }

        // When a branch is selected in the filter, scope the users list to it.
        $selectedBranchIds = array_filter((array) ($filters['branch_id'] ?? []));

        if (! empty($selectedBranchIds)) {
            $usersQuery->whereIn('branch_id', $selectedBranchIds);
        }

        $users = $usersQuery->get();

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
            $tagIds = $request->input('tag_ids', []);

            $sublimation = Sublimation::query()->create(
                collect($request->validated())->except('tag_ids')
                    ->merge(['quantity' => collect($tagIds)->sum('quantity')])
                    ->all()
            );

            if ($request->has('tag_ids')) {
                $sublimation->tags()->sync($this->tagSyncPayload($tagIds));
            }

            return redirect()->back()->with('success', 'Sublimation created successfully.');
        } catch (\Exception $e) {
            Log::error('Failed to create sublimation: '.$e->getMessage());

            return redirect()->back()->withErrors(['message' => 'An error occurred while creating the sublimation.']);
        }
    }

    public function update(UpdateSublimationRequest $request, Sublimation $sublimation): RedirectResponse
    {
        try {
            $tagIds = $request->input('tag_ids', []);

            $sublimation->fill(
                collect($request->validated())->except('tag_ids')
                    ->merge(['quantity' => collect($tagIds)->sum('quantity')])
                    ->all()
            );

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

            if ($request->has('tag_ids') && ! $sublimation->tagsLocked()) {
                $sublimation->tags()->sync($this->tagSyncPayload($tagIds));
            }

            return redirect()->back()->with('success', 'Sublimation updated successfully.');
        } catch (\Exception $e) {
            Log::error('Failed to update sublimation: '.$e->getMessage());

            return redirect()->back()->withErrors(['message' => 'An error occurred while updating the sublimation.']);
        }
    }

    private function tagSyncPayload(array $tagIds): array
    {
        return collect($tagIds)
            ->mapWithKeys(fn ($t) => [(int) $t['id'] => ['quantity' => (int) $t['quantity']]])
            ->all();
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
            Log::error('Failed to delete sublimation: '.$e->getMessage());

            return redirect()->back()->withErrors(['message' => 'An error occurred while deleting the sublimation.']);
        }
    }

    public function duplicate(Request $request, Sublimation $sublimation): RedirectResponse
    {
        $validated = $request->validate([
            'description' => ['required', 'string', 'max:255'],
            'tag_ids' => ['required', 'array', 'min:1'],
            'tag_ids.*.id' => ['required', 'integer', 'exists:tags,id'],
            'tag_ids.*.quantity' => ['required', 'integer', 'min:1'],
            'amount_total' => ['required', 'numeric', 'min:1', 'max:99999999.99'],
        ]);

        try {
            $totalQuantity = collect($validated['tag_ids'])->sum('quantity');

            $copy = Sublimation::create([
                'description' => $validated['description'],
                'notes' => $sublimation->notes,
                'branch_id' => $sublimation->branch_id,
                'customer_id' => $sublimation->customer_id,
                'amount_total' => $validated['amount_total'],
                'user_id' => $sublimation->user_id,
                'due_at' => $sublimation->due_at,
                'quantity' => $totalQuantity,
                'status' => 'for_approval',
            ]);

            $copy->tags()->sync($this->tagSyncPayload($validated['tag_ids']));

            return back()->with('success', 'Additional items added to order.');
        } catch (\Exception $e) {
            Log::error('Failed to add items to sublimation: '.$e->getMessage());

            return back()->withErrors(['message' => 'An error occurred while adding items to the order.']);
        }
    }

    public function updateStatus(Request $request, Sublimation $sublimation): RedirectResponse
    {
        $newStatus = SublimationStatus::tryFrom($request->status);
        if (! $newStatus) {
            return back()->withErrors(['status' => 'Invalid status provided.']);
        }

        try {
            if (! $sublimation->canMoveTo($newStatus)) {
                return back()->withErrors([
                    'status' => "Cannot move to '{$newStatus->value}'. Please settle the downpayment or select 'Purchase Order' / 'Authorize Production' first.",
                ]);
            }

            if ($newStatus === SublimationStatus::WAITING_FOR_DP && ! $sublimation->user_id) {
                return back()->withErrors([
                    'status' => 'A user must be assigned to the sublimation before marking it as Waiting for Downpayment.',
                ]);
            }

            DB::transaction(function () use ($sublimation, $newStatus) {
                if ($newStatus === SublimationStatus::WAITING_FOR_DP) {
                    // Check if a transaction already exists to prevent duplicates
                    if (! $sublimation->transaction()->exists()) {
                        $transactionData = $sublimation->only(['description', 'branch_id', 'customer_id', 'user_id']);

                        $transaction = $this->salesService->createTransaction(array_merge($transactionData, [
                            'invoice_number' => Transaction::generateNumber(),
                            'amount_total' => $sublimation->amount_total,
                            'particular' => 'Sublimation',
                            'staff_id' => $sublimation->user_id,
                            'transaction_date' => now(),
                        ]));

                        $sublimation->transaction_id = $transaction->id;
                    }
                }

                $sublimation->status = $newStatus;
                $sublimation->save();
            });

            return back()->with('success', 'Status updated.');
        } catch (\Exception $e) {
            Log::error("Failed to update sublimation status #{$sublimation->id}: ".$e->getMessage());

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
            Log::error('Failed to update sublimation staff: '.$e->getMessage());

            throw ValidationException::withMessages([
                'message' => 'An error occurred while updating the sublimation staff.',
            ]);
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
            Log::error('Failed to update sublimation due date: '.$e->getMessage());

            return back()->withErrors(['message' => 'An error occurred while updating the sublimation due date.']);
        }
    }
}
