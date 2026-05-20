<?php

namespace Payroll\Audit\Controllers;

use App\Http\Controllers\Controller;
use Inertia\Inertia;
use Payroll\Audit\Models\AuditLog;

class AuditLogController extends Controller
{
    public function index()
    {
        $query = AuditLog::query()->with(['user', 'branch'])->latest('created_at');

        if (auth()->user()->isAdmin() || auth()->user()->isStaff()) {
            $query->where('branch_id', auth()->user()->branch_id);
        }

        $logs = $query->paginate(50)->withQueryString();

        return Inertia::render('payroll/audit/list', [
            'logs' => $logs,
        ]);
    }
}
