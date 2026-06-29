<?php

namespace Payroll\Audit\Controllers;

use App\Http\Controllers\Controller;
use Inertia\Inertia;
use Payroll\Audit\Models\AuditLog;

class AuditLogController extends Controller
{
    public function index()
    {
        $query = AuditLog::query()->with(['user.employee:id', 'branch'])->latest('created_at');

        if (auth()->user()->isAdmin() || auth()->user()->isStaff()) {
            $query->where('branch_id', auth()->user()->branch_id);
        }

        $logs = $query->paginate(50)->withQueryString();

        $logs->getCollection()->transform(function (AuditLog $log) {
            $log->self_correction = $this->isSelfCorrection($log);

            return $log;
        });

        return Inertia::render('payroll/audit/list', [
            'logs' => $logs,
        ]);
    }

    private function isSelfCorrection(AuditLog $log): bool
    {
        if (! str_starts_with((string) $log->action, 'admin_correction')) {
            return false;
        }

        $actorEmployeeId = $log->user?->employee?->id;

        if (! $actorEmployeeId) {
            return false;
        }

        $subjectEmployeeId = $log->after['employee_id']
            ?? $log->before['employee_id']
            ?? null;

        return $subjectEmployeeId !== null
            && (int) $subjectEmployeeId === (int) $actorEmployeeId;
    }
}
