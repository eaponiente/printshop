<?php

namespace App\Http\Controllers\PurchaseOrders;

use App\Enums\PurchaseOrders\PurchaseOrderStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\PurchaseOrders\GetPurchaseOrderRequest;
use App\Http\Requests\PurchaseOrders\StorePurchaseOrderRequest;
use App\Http\Requests\PurchaseOrders\UpdatePurchaseOrderRequest;
use App\Models\Branch;
use App\Models\PurchaseOrder;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;

class PurchaseOrderController extends Controller
{
    use AuthorizesRequests;

    public function index(GetPurchaseOrderRequest $request): Response
    {
        $filters = $request->validated();

        $filters['mode'] = $filters['mode'] ?? 'monthly';
        $query = PurchaseOrder::query()
            ->withSum(['details as total_price' => function ($query) {
                $query->select(DB::raw('sum(quantity * unit_price)'));
            }], 'id')
            ->with(['details', 'branch', 'user'])
            ->where(function ($query) use ($filters) {
                $user = auth()->user();
                $filterId = $filters['branch_id'] ?? null;

                if ($user->role !== 'superadmin') {
                    // Non-admins are FORCED to their branch, regardless of the filter
                    $query->where('branch_id', $user->branch_id);
                } elseif ($filterId && $filterId !== 'all') {
                    // Superadmins only get a WHERE clause if they picked a specific branch
                    $query->where('branch_id', $filterId);
                }
            })
            ->when($filters['mode'] ?? null, function ($query, $mode) use ($filters) {
                // 1. Determine which column to filter
                $column = $filters['date_field'] ?? 'date';

                // Security: Ensure column is allowed
                if (! in_array($column, ['date', 'due_at', 'received_at'])) {
                    $column = 'date';
                }

                // 2. Get the date value from frontend (e.g., "2024-W14" or "2024-04")
                $dateValue = $filters['date'] ?? null;

                if (! $dateValue) {
                    return;
                }

                if ($mode === 'weekly') {
                    // HTML5 week input returns "YYYY-Www"
                    // Carbon can parse this using the ISO week format
                    $date = Carbon::parse($dateValue);

                    $query->whereBetween($column, [
                        $date->startOfWeek()->format('Y-m-d'),
                        $date->endOfWeek()->format('Y-m-d'),
                    ]);
                } elseif ($mode === 'monthly') {
                    // HTML5 month input returns "YYYY-MM"
                    $date = Carbon::parse($dateValue);

                    $query->whereMonth($column, $date->month)
                        ->whereYear($column, $date->year);
                } elseif ($mode === 'yearly') {
                    // HTML5 month input returns "YYYY-MM"
                    $date = Carbon::parse($dateValue);

                    $query->whereYear($column, $date);
                }
            });

        // 2. Sorting Logic
        $sortField = $filters['sort_field'] ?? 'id'; // Default sort
        $sortDirection = $filters['sort_direction'] ?? 'desc';

        $query->orderBy($sortField, $sortDirection === 'asc' ? 'asc' : 'desc');

        return Inertia::render('purchase-orders/list', [
            'filters' => $request->all(),
            'purchase_orders' => $query->paginate(30)->withQueryString(),
            'branches' => Branch::accessibleBy(auth()->user())->get(['id', 'name']),
            'statuses' => PurchaseOrderStatus::map(),
        ]);
    }

    public function store(StorePurchaseOrderRequest $request): RedirectResponse
    {
        try {
            $validated = $request->validated();

            $grandTotal = collect($validated['details'])->sum(function ($item) {
                return $item['quantity'] * $item['unit_price'];
            });

            DB::transaction(function () use ($validated, $grandTotal) {
                $po = PurchaseOrder::create([
                    'po_number' => $validated['po_number'],
                    'branch_id' => $validated['branch_id'],
                    'user_id' => auth()->id(),
                    'received_at' => $validated['received_at'],
                    'due_at' => $validated['due_at'],
                    'grand_total' => $grandTotal,
                ]);

                $po->details()->createMany($validated['details']);
            });

            return back()->with('success', 'Purchase order created successfully.');
        } catch (\Exception $e) {
            Log::error('Failed to create purchase order: '.$e->getMessage());

            return back()->withErrors(['message' => 'An error occurred while creating the purchase order.']);
        }
    }

    public function update(UpdatePurchaseOrderRequest $request, PurchaseOrder $purchaseOrder): RedirectResponse
    {
        try {
            DB::transaction(function () use ($request, $purchaseOrder) {
                if ($request->has('details')) {
                    $purchaseOrder->details()->delete();
                    $purchaseOrder->details()->createMany($request->details);

                    $grandTotal = collect($request->details)->sum(function ($item) {
                        return $item['quantity'] * $item['unit_price'];
                    });

                    $purchaseOrder->grand_total = $grandTotal;
                }

                $purchaseOrder->update($request->except('details'));
                $purchaseOrder->save();
            });

            return redirect()->back()->with('success', 'Purchase order updated successfully.');
        } catch (\Exception $e) {
            Log::error('Failed to update purchase order: '.$e->getMessage());

            return back()->withErrors(['message' => 'An error occurred while updating the purchase order.']);
        }
    }

    public function destroy(PurchaseOrder $purchaseOrder): RedirectResponse
    {
        $this->authorize('delete', auth()->user());

        try {
            $purchaseOrder->delete();

            return back()->with('success', 'Purchase order deleted successfully.');
        } catch (\Exception $e) {
            Log::error('Failed to delete purchase order: '.$e->getMessage());

            return back()->withErrors(['message' => 'An error occurred while deleting the purchase order.']);
        }
    }
}
