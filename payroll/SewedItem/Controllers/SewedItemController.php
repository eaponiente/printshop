<?php

namespace Payroll\SewedItem\Controllers;

use App\Enums\Sublimations\SublimationStatus;
use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\Payroll\SewedItem;
use App\Models\Payroll\SewedItemPayslip;
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
            'tags:id,name,color,price_per_piece',
            'sublimation:id,description,quantity,due_at,status,user_id',
            'sublimation.user:id,first_name,last_name',
            'sublimation.tags:id,name',
            'branch:id,name',
            'user:id,first_name,last_name',
        ]);

        if ($user->isAdmin()) {
            $query->where('branch_id', $user->branch_id);
        } elseif ($user->isStaff()) {
            $employee = $user->employee;
            if ($employee && $employee->can_edit_sewed_items) {
                $query->where('branch_id', $user->branch_id);
            } else {
                $query->where('user_id', $user->id);
            }
        }

        $filters = [
            'id' => $request->query('id'),
            'date_from' => $request->query('date_from'),
            'date_to' => $request->query('date_to'),
            'branch_id' => $request->query('branch_id'),
            'user_id' => $request->query('user_id'),
        ];

        // Backend-only filter by a specific sewed item id (used by deep links).
        if ($filters['id']) {
            $query->where('id', $filters['id']);
        }

        if ($filters['date_from']) {
            $query->where('sewed_date', '>=', $filters['date_from']);
        }

        if ($filters['date_to']) {
            $query->where('sewed_date', '<=', $filters['date_to']);
        }

        if ($filters['branch_id'] && $user->isSuperAdmin()) {
            $query->where('branch_id', $filters['branch_id']);
        }

        if ($filters['user_id'] && $user->isSuperAdmin()) {
            $query->where('user_id', $filters['user_id']);
        }

        if (! $request->boolean('include_completed')) {
            $query->whereNull('completed_at');
        }

        $sewedItems = $query->orderBy('created_at', 'desc')->paginate(20)->appends(array_filter($filters));

        $branches = [];
        $staff = [];

        if ($user->isSuperAdmin() || $user->isAdmin()) {
            if ($user->isSuperAdmin()) {
                $branches = Branch::orderBy('name')->get(['id', 'name']);
            } else {
                $branches = Branch::where('id', $user->branch_id)->get(['id', 'name']);
            }

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
            'filters' => array_merge($filters, [
                'include_completed' => $request->boolean('include_completed'),
            ]),
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

        $totalQuantity = 0;
        $totalAmount = 0;

        foreach ($validated['tags'] as $tagItem) {
            $qty = (int) $tagItem['quantity'];
            $price = (float) $tagItem['price_per_piece'];

            $totalQuantity += $qty;
            $totalAmount += $qty * $price;
        }

        DB::transaction(function () use ($validated, $sublimation, $totalQuantity, $totalAmount) {
            $sewedItem = SewedItem::create([
                'sublimation_id' => $sublimation->id,
                'quantity' => $totalQuantity,
                'unit_price' => $totalQuantity > 0 ? $totalAmount / $totalQuantity : null,
                'amount' => $totalAmount,
                'branch_id' => $sublimation->branch_id,
                'notes' => null,
                'sewed_date' => now()->toDateString(),
                'user_id' => auth()->id(),
            ]);

            foreach ($validated['tags'] as $tagItem) {
                $sewedItem->tags()->attach($tagItem['tag_id'], [
                    'quantity' => $tagItem['quantity'],
                    'price_per_piece' => $tagItem['price_per_piece'],
                ]);
            }

            $sublimation->status = SublimationStatus::SEWED;
            $sublimation->save();
        });

        return back()->with('success', 'Sewed item created.');
    }

    public function update(UpdateSewedItemRequest $request, SewedItem $sewedItem)
    {
        Gate::authorize('sewed-items.update', $sewedItem);

        $validated = $request->validated();

        $totalQuantity = 0;
        $totalAmount = 0;

        foreach ($validated['tags'] as $tagItem) {
            $qty = (int) $tagItem['quantity'];
            $price = (float) $tagItem['price_per_piece'];

            $totalQuantity += $qty;
            $totalAmount += $qty * $price;
        }

        DB::transaction(function () use ($sewedItem, $validated, $totalQuantity, $totalAmount) {
            $sewedItem->update([
                'quantity' => $totalQuantity,
                'unit_price' => $totalQuantity > 0 ? $totalAmount / $totalQuantity : null,
                'amount' => $totalAmount,
                'notes' => $validated['notes'] ?? null,
            ]);

            $syncData = [];
            foreach ($validated['tags'] as $tagItem) {
                $syncData[$tagItem['tag_id']] = [
                    'quantity' => $tagItem['quantity'],
                    'price_per_piece' => $tagItem['price_per_piece'],
                ];
            }
            $sewedItem->tags()->sync($syncData);
        });

        return back()->with('success', 'Sewed item updated.');
    }

    public function destroy(SewedItem $sewedItem)
    {
        Gate::authorize('sewed-items.delete', $sewedItem);

        $sewedItem->delete();

        return back()->with('success', 'Sewed item deleted.');
    }

    public function generatePayslip(Request $request)
    {
        Gate::authorize('sewed-items.viewAny');

        $validated = $request->validate([
            'sewed_item_ids' => ['required', 'array', 'min:1'],
            'sewed_item_ids.*' => ['required', 'integer', 'exists:sewed_items,id'],
        ]);

        $ids = $validated['sewed_item_ids'];

        $sewedItems = SewedItem::with(['tags', 'sublimation'])
            ->whereIn('id', $ids)
            ->get();

        $totalAmount = $sewedItems->sum('amount');

        $payslip = SewedItemPayslip::create([
            'generated_by' => auth()->id(),
            'branch_id' => auth()->user()->branch_id,
            'total_amount' => $totalAmount,
            'sewed_item_ids' => $ids,
        ]);

        session()->flash('payslip_id', $payslip->id);

        return redirect()->back()->with('success', 'Payslip generated successfully.');
    }

    public function approvePayslip(SewedItemPayslip $payslip)
    {
        Gate::authorize('sewed-items.viewAny');

        if ($payslip->status !== 'pending') {
            return back()->withErrors(['error' => 'Only pending payslips can be approved.']);
        }

        DB::transaction(function () use ($payslip) {
            $payslip->update([
                'status' => 'approved',
                'approved_by' => auth()->id(),
                'approved_at' => now(),
            ]);

            SewedItem::whereIn('id', $payslip->sewed_item_ids)
                ->update(['completed_at' => now()]);
        });

        return redirect()->back()->with('success', 'Payslip approved. Items marked as completed.');
    }

    public function cancelPayslip(SewedItemPayslip $payslip)
    {
        Gate::authorize('sewed-items.viewAny');

        if ($payslip->status !== 'pending') {
            return back()->withErrors(['error' => 'Only pending payslips can be cancelled.']);
        }

        $payslip->update(['status' => 'cancelled']);

        return redirect()->back()->with('success', 'Payslip cancelled.');
    }
}
