<?php

namespace App\Http\Controllers\Users;

use App\Http\Controllers\Controller;
use App\Http\Requests\Users\StoreEndorsementRequest;
use App\Http\Requests\Users\UpdateEndorsementRequest;
use App\Models\Branch;
use App\Models\CashOnHand;
use App\Models\Endorsement;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;

class EndorsementController extends Controller
{
    use AuthorizesRequests;

    public function index(Request $request): Response
    {
        $query = Endorsement::query()->with(['branch', 'user']);

        return Inertia::render('endorsements/list', [
            'endorsements' => $query->paginate(30)->withQueryString(),
            'branches' => Branch::accessibleBy(auth()->user())->get(['id', 'name']),
        ]);
    }

    public function store(StoreEndorsementRequest $request): RedirectResponse
    {
        try {
            Endorsement::create([
                'branch_id' => $request->branch_id,
                'amount' => $request->validated()['amount'],
                'user_id' => auth()->id(),
            ]);

            $cashOnHand = CashOnHand::where('branch_id', $request->branch_id)->first();
            $cashOnHand->decrement('amount', $request->validated()['amount']);

            return redirect()->back()->with('success', 'Endorsement created successfully.');
        } catch (\Exception $e) {
            Log::error('Failed to create endorsement: '.$e->getMessage());

            return redirect()->back()->withErrors(['error' => 'An error occurred while creating the endorsement.']);
        }
    }

    public function update(UpdateEndorsementRequest $request, Endorsement $endorsement): RedirectResponse
    {
        $this->authorize('update', auth()->user());

        try {
            $endorsement->update($request->validated());

            return redirect()->back()->with('success', 'Endorsement updated successfully.');
        } catch (\Exception $e) {
            Log::error('Failed to update endorsement: '.$e->getMessage());

            return redirect()->back()->withErrors(['error' => 'An error occurred while updating the endorsement.']);
        }
    }

    public function destroy(Endorsement $endorsement): RedirectResponse
    {
        $this->authorize('delete', auth()->user());

        try {
            $endorsement->delete();

            return redirect()->back()->with('success', 'Endorsement deleted successfully.');
        } catch (\Exception $e) {
            Log::error('Failed to delete endorsement: '.$e->getMessage());

            return redirect()->back()->withErrors(['error' => 'An error occurred while deleting the endorsement.']);
        }
    }
}
