<?php

namespace App\Http\Controllers\Users;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CustomerController extends Controller
{
    use AuthorizesRequests;

    public function index(Request $request): Response
    {
        return Inertia::render('customers/list', [
            'customers' => Customer::get(),
        ]);
    }

    public function indexApiList(Request $request)
    {
        $filters = $request->only(['customer']);

        $customersQuery = Customer::query();

        if (! empty($filters['customer'])) {
            $customersQuery->whereAny(
                ['first_name', 'last_name', 'company'],
                'like',
                '%'.$filters['customer'].'%'
            );
        }

        return $customersQuery->take(5)->get();
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'first_name' => [
                'string',
                'min:2',
                'max:255',
            ],
            'last_name' => [
                'string',
                'min:2',
                'max:255',
            ]
        ]);

        $customer = Customer::create([
            'first_name' => $validated['first_name'],
            'last_name' => $validated['last_name'] ?? null,
            'company' => $request->input('company'),
        ]);

        return back()->with('new_customer', $customer);
    }

    public function update(Request $request, Customer $customer)
    {
        $this->authorize('update', auth()->user());

        $rules = [
            'name' => [
                'required',
                'string',
                'max:255',
            ],
        ];

        $validated = $request->validate($rules);

        $customer->update($validated);

        return redirect()->back();
    }

    public function destroy(Customer $customer)
    {
        $this->authorize('delete', auth()->user());

        if ($customer->users()->count() > 0) {
            abort(403, 'You cannot delete this customer.');
        }

        $customer->delete();
    }
}
