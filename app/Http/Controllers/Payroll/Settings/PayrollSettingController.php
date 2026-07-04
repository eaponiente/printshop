<?php

namespace App\Http\Controllers\Payroll\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Payroll\Settings\UpdatePayrollSettingRequest;
use App\Models\Payroll\PayrollSetting;
use App\Services\Payroll\PayrollSettingService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Inertia\Response;
use Payroll\Audit\Models\AuditLog;
use Payroll\Audit\Traits\Auditable;

class PayrollSettingController extends Controller
{
    use Auditable, AuthorizesRequests;

    public function __construct(private PayrollSettingService $service) {}

    public function index(): Response
    {
        $this->authorize('viewAny', PayrollSetting::class);
        $models = $this->service->loadAllModels();

        $auditLogs = AuditLog::where('model_type', PayrollSetting::class)
            ->latest('created_at')
            ->limit(20)
            ->get()
            ->map(fn ($log) => [
                'action' => $log->action,
                'before' => $log->before,
                'after' => $log->after,
                'user_name' => $log->user?->name ?? 'System',
                'created_at' => $log->created_at->toDateTimeString(),
            ]);

        $settings = [];
        foreach ($models as $model) {
            $settings[$model->key] = [
                'id' => $model->id,
                'key' => $model->key,
                'value' => $model->value,
                'type' => $model->type,
                'description' => $model->description,
            ];
        }

        return inertia('payroll/settings/index', [
            'settings' => $settings,
            'auditLogs' => $auditLogs,
            'defaults' => [
                'late_deduction_per_minute' => (string) config('payroll.late_deduction_per_minute'),
                'late_deduction_threshold_minutes' => (string) config('payroll.late_deduction_threshold_minutes'),
                'half_day_threshold_minutes' => (string) config('payroll.half_day_threshold_minutes'),
                'no_break_fine' => (string) config('payroll.no_break_fine'),
            ],
        ]);
    }

    public function update(UpdatePayrollSettingRequest $request): RedirectResponse
    {
        $fields = [
            'late_deduction_per_minute',
            'late_deduction_threshold_minutes',
            'half_day_threshold_minutes',
            'no_break_fine',
        ];

        foreach ($fields as $key) {
            $model = PayrollSetting::where('key', $key)->first();

            if (! $model) {
                continue;
            }

            $before = [$key => $model->value];
            $newValue = $request->input($key) !== null ? (string) $request->input($key) : null;

            if ($newValue === $model->value) {
                continue;
            }

            $this->service->set($key, $newValue);
            $model->refresh();

            $this->audit('updated', $model, $before, [$key => $model->value]);
        }

        return back()->with('success', 'Payroll settings updated.');
    }
}
