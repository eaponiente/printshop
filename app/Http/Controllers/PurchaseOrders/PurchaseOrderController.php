<?php

namespace App\Http\Controllers\PurchaseOrders;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\PurchaseOrder;
use App\Models\Tag;
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
            'particular' => ['required', 'string', 'max:255'],
            'branch_id' => ['required', Rule::exists('branches', 'id')],
            'status' => ['required', 'string'],

            // Detail (Items) Validation
            'items' => ['required', 'array', 'min:1'], // Must have at least one item
            'items.*.item_name' => ['required', 'string', 'max:255'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'items.*.unit_price' => ['required', 'numeric', 'min:0'],
        ]);

        $po = PurchaseOrder::create(
            array_merge($request->only(['particular', 'branch_id']), [
                'user_id' => auth()->id(),
                'ordered_at' => now(),
            ])
        );

        // Save the many-to-one relationship
        $po->details()->createMany($request->items);

        return back();
    }

    public function update(Request $request, PurchaseOrder $purchaseOrder)
    {
        $this->authorize('update', auth()->user());

        $validated = $request->validate([
            'status' => [
                'required',
            ],
        ]);

        $purchaseOrder->update($validated);

        return redirect()->back();
    }

    public function destroy(Tag $tag)
    {
        $this->authorize('delete', auth()->user());

        $tag->delete();
    }
}
