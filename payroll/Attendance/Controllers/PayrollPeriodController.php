<?php

namespace Payroll\Attendance\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\Payroll\CompanyConfig;
use App\Models\Payroll\PayrollPeriod;
use App\Models\Payroll\PayrollPeriodItem;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Payroll\Attendance\Enums\PayrollPeriodStatus;
use Payroll\Attendance\Services\PayrollPeriodService;
use Payroll\Audit\Traits\Auditable;

class PayrollPeriodController extends Controller
{
    use Auditable;
    use AuthorizesRequests;

    public function index(Request $request)
    {
        $user = auth()->user();
        $query = PayrollPeriod::query()->with('branch');

        if ($user->isAdmin()) {
            $query->where('branch_id', $user->branch_id);
        } elseif ($user->isStaff()) {
            return redirect()->route('payroll.index');
        }

        $periods = $query->orderBy('period_start', 'desc')->paginate(20);

        return Inertia::render('payroll/payroll/periods', [
            'periods' => $periods,
            'statuses' => collect(PayrollPeriodStatus::cases())->map(fn ($s) => [
                'key' => $s->value,
                'value' => $s->label(),
            ])->values(),
            'canGenerate' => ! $user->isStaff(),
            'isSuperAdmin' => $user->isSuperAdmin(),
            'branches' => $user->isSuperAdmin() ? Branch::orderBy('name')->get(['id', 'name']) : [],
        ]);
    }

    public function generate(Request $request, PayrollPeriodService $service)
    {
        $user = auth()->user();

        $validated = $request->validate([
            'period_start' => ['required', 'date'],
            'period_end' => ['required', 'date', 'after:period_start'],
            'branch_id' => $user->isSuperAdmin() ? ['required', 'exists:branches,id'] : ['nullable'],
        ]);

        $branchId = $user->isSuperAdmin()
            ? $validated['branch_id']
            : $user->branch_id;

        Gate::authorize('payroll-periods.generate', [(int) $branchId]);

        $branch = Branch::findOrFail($branchId);

        try {
            $period = $service->generate($branch, $validated['period_start'], $validated['period_end']);
        } catch (QueryException $e) {
            if (str_contains($e->getMessage(), 'Duplicate entry') || str_contains($e->getMessage(), 'UNIQUE constraint failed')) {
                return back()->withErrors(['error' => 'A payroll period already exists for this branch and date range.']);
            }
            throw $e;
        }

        return redirect()->route('payroll.periods.show', $period)
            ->with('success', 'Payroll period generated successfully.');
    }

    public function show(PayrollPeriod $period, PayrollPeriodService $service)
    {
        Gate::authorize('payroll-periods.view', [$period->branch_id]);

        $period->load('branch');

        $items = $period->items()
            ->with('employee:id,first_name,last_name,employee_number,current_daily_rate,sss_number,philhealth_number,pagibig_number')
            ->paginate(50)
            ->withQueryString();

        $incompleteSheets = $period->checked_at
            ? $service->findIncompleteSheets($period->branch, $period->period_start->toDateString(), $period->period_end->toDateString())
            : [];

        return Inertia::render('payroll/payroll/period-show', [
            'period' => array_merge($period->toArray(), [
                'checked_at' => $period->checked_at?->toDateTimeString(),
            ]),
            'items' => $items,
            'incompleteSheets' => $incompleteSheets,
            'canApprove' => Gate::check('payroll-periods.approve', [$period->branch_id]),
            'canDelete' => ! auth()->user()->isStaff(),
            'isSuperAdmin' => auth()->user()->isSuperAdmin(),
        ]);
    }

    public function check(PayrollPeriod $period)
    {
        Gate::authorize('payroll-periods.approve', [$period->branch_id]);

        $period->update(['checked_at' => now()]);

        return redirect()->route('payroll.periods.show', $period);
    }

    public function approve(PayrollPeriod $period, PayrollPeriodService $service)
    {
        Gate::authorize('payroll-periods.approve', [$period->branch_id]);

        $service->approve($period, auth()->id());

        return back()->with('success', 'Payroll period approved.');
    }

    public function void(PayrollPeriod $period, PayrollPeriodService $service)
    {
        Gate::authorize('payroll-periods.void');

        $service->void($period);

        return back()->with('success', 'Payroll period voided. Sheets unlocked for corrections.');
    }

    public function destroy(PayrollPeriod $period, PayrollPeriodService $service)
    {
        Gate::authorize('payroll-periods.delete', [(int) $period->branch_id]);

        $before = $period->getAttributes();
        $service->delete($period);

        $this->audit('deleted', $period, $before, []);

        return redirect()->route('payroll.periods.index')
            ->with('success', 'Draft payroll period deleted successfully.');
    }

    public function payslip(PayrollPeriod $period, PayrollPeriodItem $item)
    {
        $user = auth()->user();
        if ($user->isStaff() && $user->employee_id !== $item->employee_id) {
            abort(403);
        }

        $item->load([
            'employee:id,first_name,last_name,employee_number,current_daily_rate,sss_number,philhealth_number,pagibig_number,tin_number,position',
            'payrollPeriod:id,branch_id,period_start,period_end,status,approved_by,approved_at',
            'payrollPeriod.branch:id,name',
        ]);

        $period->load([
            'branch:id,name',
            'approvedBy:id,first_name,last_name',
        ]);

        return Inertia::render('payroll/payroll/payslip', [
            'period' => $period,
            'item' => $item,
            'companyName' => CompanyConfig::getValue('company_name', 'Company Name'),
            'viewerCanSeeEmployer' => $user->isSuperAdmin() || $user->isAdmin(),
        ]);
    }

    public function myPayslip()
    {
        $user = auth()->user();
        if (! $user->employee_id) {
            return redirect()->route('payroll.index')
                ->with('error', 'No employee record linked to your account.');
        }

        $items = PayrollPeriodItem::where('employee_id', $user->employee_id)
            ->with(['payrollPeriod' => fn ($q) => $q->where('status', '!=', 'voided')])
            ->whereHas('payrollPeriod', fn ($q) => $q->where('status', '!=', 'voided'))
            ->latest('id')
            ->paginate(20);

        if ($items->isEmpty()) {
            return Inertia::render('payroll/payroll/no-payslip');
        }

        return Inertia::render('payroll/payroll/my-payslip', [
            'payslips' => $items,
        ]);
    }
}
