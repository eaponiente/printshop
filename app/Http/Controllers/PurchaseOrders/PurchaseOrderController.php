<?php

namespace App\Http\Controllers\PurchaseOrders;

use App\Enums\PurchaseOrders\PurchaseOrderStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\PurchaseOrders\StorePurchaseOrderRequest;
use App\Http\Requests\PurchaseOrders\UpdatePurchaseOrderRequest;
use App\Models\Branch;
use App\Models\PurchaseOrder;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;

class PurchaseOrderController extends Controller
{
    use AuthorizesRequests;

    public function index(Request $request): Response
    {
        $query = PurchaseOrder::query()
            ->withSum(['details as total_price' => function ($query) {
                $query->select(DB::raw('sum(quantity * unit_price)'));
            }], 'id')
            ->with(['details', 'branch', 'user']);

        return Inertia::render('purchase-orders/list', [
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
                    'received_at' => now(),
                    'grand_total' => $grandTotal,
                ]);

                $po->details()->createMany($validated['details']);
            });

            return back()->with('success', 'Purchase order created successfully.');
        } catch (\Exception $e) {
            Log::error('Failed to create purchase order: '.$e->getMessage());

            return back()->withErrors(['error' => 'An error occurred while creating the purchase order.']);
        }
    }

    public function update(UpdatePurchaseOrderRequest $request, PurchaseOrder $purchaseOrder): RedirectResponse
    {
        $this->authorize('update', auth()->user());

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

                $purchaseOrder->status = $request->status;
                $purchaseOrder->save();
            });

            return redirect()->back()->with('success', 'Purchase order updated successfully.');
        } catch (\Exception $e) {
            Log::error('Failed to update purchase order: '.$e->getMessage());

            return back()->withErrors(['error' => 'An error occurred while updating the purchase order.']);
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

            return back()->withErrors(['error' => 'An error occurred while deleting the purchase order.']);
        }
    }
}
