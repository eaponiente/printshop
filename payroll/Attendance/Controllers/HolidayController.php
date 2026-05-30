<?php

namespace Payroll\Attendance\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Payroll\Holiday;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Payroll\Attendance\Enums\HolidayType;

class HolidayController extends Controller
{
    use AuthorizesRequests;

    public function index()
    {
        $this->authorize('viewAny', Holiday::class);

        $holidays = Holiday::orderBy('date')->paginate(50);

        return Inertia::render('payroll/holidays/list', [
            'holidays' => $holidays,
            'types' => collect(HolidayType::cases())->map(fn ($t) => [
                'key' => $t->value,
                'value' => $t->label(),
            ])->values(),
        ]);
    }

    public function store(Request $request)
    {
        $this->authorize('create', Holiday::class);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:200'],
            'date' => ['required', 'date'],
            'type' => ['required', 'string', 'in:regular,special'],
            'recurring' => ['boolean'],
        ]);

        Holiday::create($validated);

        return back()->with('success', 'Holiday added successfully.');
    }

    public function update(Request $request, Holiday $holiday)
    {
        $this->authorize('update', $holiday);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:200'],
            'date' => ['required', 'date'],
            'type' => ['required', 'string', 'in:regular,special'],
            'recurring' => ['boolean'],
        ]);

        $holiday->update($validated);

        return back()->with('success', 'Holiday updated successfully.');
    }

    public function destroy(Holiday $holiday)
    {
        $this->authorize('delete', $holiday);

        $holiday->delete();

        return back()->with('success', 'Holiday removed successfully.');
    }
}
