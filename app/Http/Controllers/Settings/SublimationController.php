<?php

namespace App\Http\Controllers\Settings;

use App\Enums\Sales\TransactionStatus;
use App\Enums\Sublimations\SublimationStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\StoreSublimationRequest;
use App\Http\Requests\Settings\UpdateSublimationRequest;
use App\Models\Branch;
use App\Models\Sublimation;
use App\Models\Tag;
use App\Models\Transaction;
use App\Models\User;
use App\Services\Sales\SalesService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class SublimationController extends Controller
{
    use AuthorizesRequests;

    public function __construct(protected SalesService $salesService) {}

    public function index(Request $request): Response
    {
        $query = Sublimation::with('tags', 'branch', 'user', 'customer', 'transaction');

        $query->when($request->filled('branch_id') && $request->branch_id !== 'all', function ($q) use ($request) {
            $q->where('branch_id', $request->branch_id);
        });

        $filters = $request->all();

        $query->where(function ($query) use ($filters) {
            $user = auth()->user();
            $filterId = $filters['branch_id'] ?? null;

            if ($user->role !== 'superadmin') {
                // Non-admins are FORCED to their branch, regardless of the filter
                $query->where('branch_id', $user->branch_id);
            } elseif ($filterId && $filterId !== 'all') {
                // Superadmins only get a WHERE clause if they picked a specific branch
                $query->where('branch_id', $filterId);
            }
        });

        $query->when($request->filled('status') && $request->status !== 'all', function ($q) use ($request) {
            $q->whereIn('status', $request->status);
        });

        $query->when(
            ! $request->boolean('include_completed'),
            function ($q) {
                // If we are NOT including completed, we filter them out
                $q->where('status', '!=', 'completed');
            }
        );

        $query->when($request->filled('user_id') && $request->user_id !== 'all', function ($q) use ($request) {
            $q->where('user_id', $request->user_id);
        });

        // 2. Sorting Logic
        $sortField = $request->query('sort_field', 'due_at'); // Default sort
        $sortDirection = $request->query('sort_direction', 'desc');

        // Whitelist allowed sortable columns
        $allowedSorts = ['due_at'];

        if (in_array($sortField, $allowedSorts)) {
            $query->orderBy($sortField, $sortDirection === 'asc' ? 'asc' : 'desc');
        }

        // get all users filtered by branch
        $branchId = $request->query('branch_id');
        $users = User::whereIn('role', ['admin', 'staff'])->get();

        return Inertia::render('sublimations/list', [
            'sublimations' => $query->paginate(30)->withQueryString(),
            'availableTags' => Tag::all(['id', 'name', 'color']),
            'filters' => $request->all(),
            'branches' => Branch::accessibleBy(auth()->user())->get(['id', 'name']),
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

                if ($sublimation->transaction()->exists()) {
                    if ($sublimation->transaction->status != TransactionStatus::PENDING->value) {
                        return back()->withErrors(['message' => 'Cannot change amount on processed sublimations.']);
                    }

                    $sublimation->transaction->update([
                        'amount_total' => $sublimation->amount_total,
                    ]);
                }


                // Custom logic: e.g., only allow change if status is pending
                if ($sublimation->status->isProductionPhase()) {
                    return back()->withErrors(['message' => 'Cannot change amount on processed sublimations.']);
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
        $this->authorize('delete', auth()->user());

        try {
            if (! $sublimation->status->isPrePaymentPhase()) {
                return redirect()->back()->withErrors(['message' => 'You cannot delete this sublimation because it is not in the pre-payment phase.']);
            }

            foreach ($sublimation->images as $image) {
                if (Storage::disk('public')->exists($image->url)) {
                    Storage::disk('public')->delete($image->url);
                }
            }

            $sublimation->delete();

            return redirect()->back()->with('success', 'Sublimation deleted successfully.');
        } catch (\Exception $e) {
            Log::error('Failed to delete sublimation: ' . $e->getMessage());

            return redirect()->back()->withErrors(['message' => 'An error occurred while deleting the sublimation.']);
        }
    }

    public function updateStatus(Request $request, Sublimation $sublimation): RedirectResponse
    {
        $newStatus = SublimationStatus::from($request->status);

        try {
            if (! $sublimation->canMoveTo($newStatus)) {
                return back()->withErrors([
                    'status' => "Cannot move to '{$newStatus->value}'. Please settle the downpayment or select 'Purchase Order' / 'Authorize Production' first.",
                ]);
            }

            if ($newStatus === SublimationStatus::WAITING_FOR_DP) {
                // Check if a transaction already exists to prevent duplicates
                if (! $sublimation->transaction()->exists()) {
                    $transactionData = $sublimation->only(['description', 'branch_id', 'customer_id', 'user_id']);

                    $transaction = $this->salesService->createTransaction(array_merge($transactionData, [
                        'invoice_number' => Transaction::generateNumber(),
                        'amount_total' => $sublimation->amount_total,
                        'particular' => 'Sublimation',
                        'staff_id' => auth()->id(),
                        'transaction_date' => now(),
                    ]));

                    $sublimation->transaction_id = $transaction->id;
                }
            }

            $sublimation->status = $newStatus;
            $sublimation->save();

            return back()->with('success', 'Status updated.');
        } catch (\Exception $e) {
            return back()->withErrors(['status' => 'The status change is not allowed at this time.']);
        }
    }
}
