<?php

namespace App\Http\Controllers\Incentives;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\Incentive;
use App\Services\Incentives\IncentiveService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class IncentiveController extends Controller
{
    use AuthorizesRequests;

    public function __construct(protected IncentiveService $incentiveService) {}

    public function index(Request $request): Response
    {
        $user = auth()->user();

        if (! $user->isSuperAdmin()) {
            abort(403);
        }

        $month = (int) ($request->input('month', now()->month));
        $year = (int) ($request->input('year', now()->year));
        $branchId = $request->input('branch_id', $user->isSuperAdmin() ? 'all' : $user->branch_id);

        $incentives = $this->incentiveService->getBranchIncentives($month, $year, $branchId);

        $branches = Branch::query()
            ->when($user->isAdmin(), fn ($q) => $q->where('id', $user->branch_id))
            ->get(['id', 'name']);

        $history = $this->incentiveService->getIncentiveHistory($branchId);

        return Inertia::render('incentives/list', [
            'filters' => [
                'month' => $month,
                'year' => $year,
                'branch_id' => $branchId,
            ],
            'incentives' => $incentives,
            'branches' => $branches,
            'history' => $history,
        ]);
    }

    public function pay(Request $request): RedirectResponse
    {
        $this->authorize('pay', Incentive::class);

        $validated = $request->validate([
            'branch_id' => ['required', 'exists:branches,id'],
            'user_id' => ['required', 'exists:users,id'],
            'month' => ['required', 'integer', 'min:1', 'max:12'],
            'year' => ['required', 'integer', 'min:2000', 'max:2100'],
            'incentive_amount' => ['required', 'numeric', 'min:0'],
        ]);

        $this->incentiveService->payIncentive(
            (int) $validated['branch_id'],
            (int) $validated['user_id'],
            (int) $validated['month'],
            (int) $validated['year'],
            (float) $validated['incentive_amount']
        );

        return back()->with('success', 'Incentive paid successfully.');
    }
}
