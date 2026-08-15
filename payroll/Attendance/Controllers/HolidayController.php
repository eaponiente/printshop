<?php

namespace Payroll\Attendance\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\Payroll\Holiday;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Inertia\Inertia;
use Payroll\Attendance\Enums\HolidayType;
use Payroll\Attendance\Requests\StoreHolidayRequest;
use Payroll\Attendance\Requests\UpdateHolidayRequest;
use Payroll\Attendance\Services\HolidayService;

class HolidayController extends Controller
{
    use AuthorizesRequests;

    public function index()
    {
        $this->authorize('viewAny', Holiday::class);

        // Upcoming holidays (today included) sort first, chronologically; those
        // that have already passed sink below, still chronological among
        // themselves. Ordering lives in the query so it survives pagination.
        $today = now()->toDateString();

        $holidays = Holiday::with('branches:id,name')
            ->orderByRaw('CASE WHEN date < ? THEN 1 ELSE 0 END', [$today])
            ->orderBy('date')
            ->paginate(50);

        return Inertia::render('payroll/holidays/list', [
            'holidays' => $holidays,
            'types' => collect(HolidayType::cases())->map(fn ($t) => [
                'key' => $t->value,
                'value' => $t->label(),
            ])->values(),
            'branches' => Branch::orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function store(StoreHolidayRequest $request, HolidayService $service)
    {
        $service->create($request->safe()->except('branch_ids'), $request->validated('branch_ids', []));

        return back()->with('success', 'Holiday added successfully.');
    }

    public function update(UpdateHolidayRequest $request, Holiday $holiday, HolidayService $service)
    {
        $service->update($holiday, $request->safe()->except('branch_ids'), $request->validated('branch_ids', []));

        return back()->with('success', 'Holiday updated successfully.');
    }

    public function destroy(Holiday $holiday, HolidayService $service)
    {
        $this->authorize('delete', $holiday);

        $service->delete($holiday);

        return back()->with('success', 'Holiday removed successfully.');
    }
}
