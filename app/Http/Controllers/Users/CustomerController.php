<?php

namespace App\Http\Controllers\Users;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use App\Http\Requests\Users\StoreCustomerRequest;
use App\Http\Requests\Users\UpdateCustomerRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Log;
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

        return response()->json($customersQuery->take(5)->get());
    }

    public function store(StoreCustomerRequest $request): RedirectResponse
    {
        try {
            $customer = Customer::create($request->validated());

            return back()->with('new_customer', $customer)->with('success', 'Customer created successfully.');
        } catch (\Exception $e) {
            Log::error('Failed to create customer: ' . $e->getMessage());
            return back()->withErrors(['error' => 'An error occurred while creating the customer.']);
        }
    }

    public function update(UpdateCustomerRequest $request, Customer $customer): RedirectResponse
    {
        $this->authorize('update', auth()->user());

        try {
            $customer->update($request->validated());

            return back()->with('message', 'Customer updated successfully.')->with('success', 'Customer updated successfully.');
        } catch (\Exception $e) {
            Log::error('Failed to update customer: ' . $e->getMessage());
            return back()->withErrors(['error' => 'An error occurred while updating the customer.']);
        }
    }

    /**
     * @throws AuthorizationException
     */
    public function destroy(Customer $customer): RedirectResponse
    {
        $this->authorize('delete', auth()->user());

        // Check if the customer has any transactions
        if ($customer->transactions()->exists()) {
            return back()->withErrors([
                'delete' => 'Cannot delete customer because they have existing transaction records.',
            ]);
        }

        try {
            $customer->delete();
            return back()->with('success', 'Customer deleted successfully.');
        } catch (\Exception $e) {
            Log::error('Failed to delete customer: ' . $e->getMessage());
            return back()->withErrors(['error' => 'An error occurred while deleting the customer.']);
        }
    }
}
