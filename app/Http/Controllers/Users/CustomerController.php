<?php

namespace App\Http\Controllers\Users;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use Illuminate\Auth\Access\AuthorizationException;
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
            'customers' => Customer::paginate(50)->withQueryString(),
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
            'first_name' => ['required', 'string', 'min:2', 'max:255'],
            'last_name' => ['required', 'string', 'min:2', 'max:255'],
            'company' => ['nullable', 'string', 'min:2', 'max:255'],
        ]);

        $customer = Customer::create($validated);

        return back()->with('new_customer', $customer);
    }

    public function update(Request $request, Customer $customer)
    {
        $this->authorize('update', auth()->user());

        $validated = $request->validate([
            'first_name' => ['required', 'string', 'min:2', 'max:255'],
            'last_name' => ['required', 'string', 'min:2', 'max:255'],
            'company' => ['nullable', 'string', 'min:2', 'max:255'],
        ]);

        // 2. Update the record
        $customer->update($validated);

        // 3. Return with a success flash message
        return back()->with('message', 'Customer updated successfully.');
    }

    /**
     * @throws AuthorizationException
     */
    public function destroy(Customer $customer)
    {
        $this->authorize('delete', auth()->user());

        // Check if the customer has any transactions
        if ($customer->transactions()->exists()) {
            return back()->withErrors([
                'delete' => 'Cannot delete customer because they have existing transaction records.',
            ]);
        }

        $customer->delete();
    }
}
