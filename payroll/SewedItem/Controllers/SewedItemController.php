<?php

namespace Payroll\SewedItem\Controllers;

use App\Enums\Sublimations\SublimationStatus;
use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\Payroll\SewedItem;
use App\Models\Sublimation;
use App\Models\User;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Payroll\SewedItem\Requests\StoreSewedItemRequest;
use Payroll\SewedItem\Requests\UpdateSewedItemRequest;

class SewedItemController extends Controller
{
    use AuthorizesRequests;

    public function index(Request $request)
    {
        $user = auth()->user();

        Gate::authorize('sewed-items.viewAny');

        $query = SewedItem::with([
            'sublimation:id,description,quantity,due_at,status,user_id',
            'sublimation.user:id,first_name,last_name',
            'sublimation.tags:id,name',
            'branch:id,name',
            'user:id,first_name,last_name',
        ]);

        if ($user->isAdmin()) {
            $query->where('branch_id', $user->branch_id);
        } elseif ($user->isStaff()) {
            $query->where('user_id', $user->id);
        }

        $filters = [
            'date_from' => $request->query('date_from'),
            'date_to' => $request->query('date_to'),
            'branch_id' => $request->query('branch_id'),
            'user_id' => $request->query('user_id'),
        ];

        if ($filters['date_from']) {
            $query->where('sewed_date', '>=', $filters['date_from']);
        }

        if ($filters['date_to']) {
            $query->where('sewed_date', '<=', $filters['date_to']);
        }

        if ($filters['branch_id'] && ($user->isSuperAdmin() || $user->isAdmin())) {
            $query->where('branch_id', $filters['branch_id']);
        }

        if ($filters['user_id'] && $user->isSuperAdmin()) {
            $query->where('user_id', $filters['user_id']);
        }

        $sewedItems = $query->orderBy('sewed_date', 'desc')->paginate(20)->appends(array_filter($filters));

        $branches = [];
        $staff = [];

        if ($user->isSuperAdmin() || $user->isAdmin()) {
            $branches = Branch::orderBy('name')->get(['id', 'name']);

            $selectedBranchId = $filters['branch_id'] ?: ($user->isAdmin() ? $user->branch_id : null);

            $staffQuery = User::where('role', 'staff');

            if ($user->isAdmin()) {
                $staffQuery->where('branch_id', $user->branch_id);
            } elseif ($selectedBranchId) {
                $staffQuery->where('branch_id', $selectedBranchId);
            }

            $staff = $staffQuery->orderBy('last_name')->get(['id', 'first_name', 'last_name']);
        }

        return Inertia::render('payroll/sewed-items/index', [
            'sewedItems' => $sewedItems,
            'filters' => $filters,
            'branches' => $branches,
            'staff' => $staff,
        ]);
    }

    public function store(StoreSewedItemRequest $request)
    {
        Gate::authorize('sewed-items.create');

        $validated = $request->validated();

        $sublimation = Sublimation::findOrFail($validated['sublimation_id']);

        if (SewedItem::where('sublimation_id', $sublimation->id)->exists()) {
            return back()->withErrors(['error' => 'A sewed item already exists for this sublimation.']);
        }

        DB::transaction(function () use ($validated, $sublimation) {
            SewedItem::create([
                'sublimation_id' => $sublimation->id,
                'quantity' => $validated['quantity'],
                'unit_price' => $validated['unit_price'],
                'amount' => $validated['quantity'] * $validated['unit_price'],
                'branch_id' => $sublimation->branch_id,
                'notes' => null,
                'sewed_date' => now()->toDateString(),
                'user_id' => auth()->id(),
            ]);

            $sublimation->status = SublimationStatus::SEWED;
            $sublimation->save();
        });

        return back()->with('success', 'Sewed item created.');
    }

    public function update(UpdateSewedItemRequest $request, SewedItem $sewedItem)
    {
        Gate::authorize('sewed-items.update', $sewedItem);

        $validated = $request->validated();

        $sewedItem->update([
            'quantity' => $validated['quantity'],
            'unit_price' => $validated['unit_price'],
            'amount' => $validated['quantity'] * $validated['unit_price'],
            'notes' => $validated['notes'] ?? null,
        ]);

        return back()->with('success', 'Sewed item updated.');
    }

    public function destroy(SewedItem $sewedItem)
    {
        Gate::authorize('sewed-items.delete', $sewedItem);

        $sewedItem->delete();

        return back()->with('success', 'Sewed item deleted.');
    }
}
