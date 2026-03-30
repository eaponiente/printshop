<?php

namespace App\Http\Controllers\PurchaseOrders;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\PurchaseOrder;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class PurchaseOrderController extends Controller
{
    use AuthorizesRequests;

    public function index(Request $request): Response
    {
        $branches = Branch::all();

        $query = PurchaseOrder::query()
            ->withSum(['details as total_price' => function ($query) {
                $query->select(DB::raw('sum(quantity * unit_price)'));
            }], 'id')
            ->with(['details', 'branch', 'user']);

        return Inertia::render('purchase-orders/list', [
            'purchase_orders' => $query->paginate(30)->withQueryString(),
            'branches' => $branches,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            // Master Record Validation
            'po_number' => ['required', 'string', 'max:255'],
            'branch_id' => ['required', Rule::exists('branches', 'id')],
            'status' => ['required', 'string'],

            // Detail (Items) Validation
            'details' => ['required', 'array', 'min:1'], // Must have at least one item
            'details.*.item_name' => ['required', 'string', 'max:255'],
            'details.*.quantity' => ['required', 'integer', 'min:1'],
            'details.*.unit_price' => ['required', 'numeric', 'min:1'],
        ]);

        // Calculate Grand Total from details
        $grandTotal = collect($request->details)->sum(function ($item) {
            return $item['quantity'] * $item['unit_price'];
        });

        return DB::transaction(function () use ($request, $grandTotal) {
            $po = PurchaseOrder::create([
                'po_number' => $request->po_number,
                'branch_id' => $request->branch_id,
                'status' => $request->status,
                'user_id' => auth()->id(),
                'ordered_at' => now(),
                'grand_total' => $grandTotal, // Set calculated total
            ]);

            $po->details()->createMany($request->details);

            return back();
        });
    }

    public function update(Request $request, PurchaseOrder $purchaseOrder)
    {
        $this->authorize('update', auth()->user());

        // Add item validation here if you are allowing item editing during update
        $validated = $request->validate([
            'status' => ['required'],
            'po_number' => ['sometimes', 'string'],
            'details' => ['sometimes', 'array'],
            'details.*.quantity' => ['required_with:details', 'integer', 'gte:1'],
            'details.*.unit_price' => ['required_with:details', 'numeric', 'gte:1'],
        ]);

        return DB::transaction(function () use ($request, $purchaseOrder) {
            // Update details if provided in the request
            if ($request->has('details')) {
                $purchaseOrder->details()->delete(); // Clear old details
                $purchaseOrder->details()->createMany($request->details);

                // Recalculate Grand Total
                $grandTotal = collect($request->details)->sum(function ($item) {
                    return $item['quantity'] * $item['unit_price'];
                });

                $purchaseOrder->grand_total = $grandTotal;
            }

            $purchaseOrder->status = $request->status;
            $purchaseOrder->save();

            return redirect()->back();

        });
    }

    public function destroy(PurchaseOrder $purchaseOrder)
    {
        $this->authorize('delete', auth()->user());

        $purchaseOrder->delete();

        return back();

    }
}
