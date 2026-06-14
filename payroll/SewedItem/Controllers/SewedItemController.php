<?php

namespace Payroll\SewedItem\Controllers;

use App\Enums\Sublimations\SublimationStatus;
use App\Http\Controllers\Controller;
use App\Models\Payroll\SewedItem;
use App\Models\Sublimation;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Payroll\SewedItem\Requests\StoreSewedItemRequest;
use Payroll\SewedItem\Requests\UpdateSewedItemRequest;

class SewedItemController extends Controller
{
    use AuthorizesRequests;

    public function index()
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

        $sewedItems = $query->orderBy('sewed_date', 'desc')->paginate(20);

        return Inertia::render('payroll/sewed-items/index', [
            'sewedItems' => $sewedItems,
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
