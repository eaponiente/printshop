<?php

namespace Payroll\Attendance\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\Payroll\PayrollPeriod;
use App\Models\Payroll\PayrollPeriodItem;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Payroll\Attendance\Enums\PayrollPeriodStatus;
use Payroll\Attendance\Requests\PrintPayslipsRequest;

class PayrollReportController extends Controller
{
    use AuthorizesRequests;

    public function index(Request $request)
    {
        $user = auth()->user();

        if ($user->isStaff()) {
            abort(403);
        }

        $branches = $user->isSuperAdmin()
            ? Branch::orderBy('name')->get(['id', 'name'])
            : Branch::accessibleBy($user)->get(['id', 'name']);

        $periodsQuery = PayrollPeriod::query()
            ->whereIn('status', [PayrollPeriodStatus::APPROVED->value, PayrollPeriodStatus::PAID->value]);

        if (! $user->isSuperAdmin()) {
            $branchIds = $branches->pluck('id');
            $periodsQuery->whereIn('branch_id', $branchIds);
        }

        $periods = $periodsQuery
            ->with('branch:id,name')
            ->orderBy('period_start', 'desc')
            ->get(['id', 'branch_id', 'period_start', 'period_end', 'status']);

        return Inertia::render('payroll/reports/index', [
            'branches' => $branches,
            'periods' => $periods,
        ]);
    }

    public function print(PrintPayslipsRequest $request)
    {
        $user = auth()->user();

        if ($user->isStaff()) {
            abort(403);
        }

        $validated = $request->validated();
        $branchId = (int) $validated['branch_id'];
        $periodId = (int) $validated['period_id'];

        Gate::authorize('payroll-periods.view', [$branchId]);

        $period = PayrollPeriod::with('branch:id,name')->findOrFail($periodId);

        $items = PayrollPeriodItem::where('payroll_period_id', $periodId)
            ->with([
                'employee:id,first_name,last_name,employee_number,current_daily_rate,sss_number,philhealth_number,pagibig_number,tin_number,position',
            ])
            ->orderBy('id')
            ->get();

        return Inertia::render('payroll/reports/print', [
            'period' => [
                'id' => $period->id,
                'branch' => $period->branch->name,
                'period_start' => $period->period_start->format('Y-m-d'),
                'period_end' => $period->period_end->format('Y-m-d'),
                'status' => $period->status->label(),
            ],
            'items' => $items->map(fn (PayrollPeriodItem $item) => [
                'id' => $item->id,
                'employee' => [
                    'id' => $item->employee->id,
                    'first_name' => $item->employee->first_name,
                    'last_name' => $item->employee->last_name,
                    'employee_number' => $item->employee->employee_number,
                    'current_daily_rate' => (float) $item->employee->current_daily_rate,
                    'sss_number' => $item->employee->sss_number,
                    'philhealth_number' => $item->employee->philhealth_number,
                    'pagibig_number' => $item->employee->pagibig_number,
                    'tin_number' => $item->employee->tin_number,
                    'position' => $item->employee->position->value,
                ],
                'total_regular_days' => $item->total_regular_days,
                'absent_days' => $item->absent_days,
                'total_late_minutes' => $item->total_late_minutes,
                'late_deduction' => (float) $item->late_deduction,
                'total_undertime_minutes' => $item->total_undertime_minutes,
                'undertime_deduction' => (float) $item->undertime_deduction,
                'total_overtime_minutes' => $item->total_overtime_minutes,
                'overtime_pay' => (float) $item->overtime_pay,
                'holiday_pay_days' => $item->holiday_pay_days,
                'holiday_pay' => (float) $item->holiday_pay,
                'leave_paid_days' => $item->leave_paid_days,
                'fine_deduction' => (float) $item->fine_deduction,
                'gross_pay' => (float) $item->gross_pay,
                'deminimis_earnings' => (float) $item->deminimis_earnings,
                'sss_deduction' => (float) $item->sss_deduction,
                'philhealth_deduction' => (float) $item->philhealth_deduction,
                'pagibig_deduction' => (float) $item->pagibig_deduction,
                'ca_deduction' => (float) $item->ca_deduction,
                'net_pay' => (float) $item->net_pay,
                'daily_rate' => (float) $item->daily_rate,
                'sss_bracket' => $item->sss_bracket,
            ]),
        ]);
    }
}
