<?php

namespace App\Http\Controllers\Users;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\Endorsement;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
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
            'branches' => Branch::all(),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'amount' => [
                'required',
                'numeric',
                'min:0',
            ],
            'branch_id' => 'required|exists:branches,id',
        ]);

        auth()->user()->endorsements()->create($validated);

        return redirect()->back();
    }

    public function update(Request $request, Endorsement $endorsement)
    {
        $this->authorize('update', auth()->user());

        $validated = $request->validate([
            'amount' => ['required', 'numeric', 'min:0'],
            'branch_id' => ['required', 'exists:branches,id'],
        ]);

        $endorsement->update($validated);

        return redirect()->back();
    }

    public function destroy(Endorsement $endorsement)
    {
        $this->authorize('delete', auth()->user());

        $endorsement->delete();
    }
}
