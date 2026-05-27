<?php

namespace Payroll\Audit\Traits;

use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Payroll\Audit\Models\AuditLog;

trait Auditable
{
    protected function audit(string $action, Model $model, array $before = [], array $after = []): void
    {
        try {
            $before = $this->normalizeValues($before);
            $after = $this->normalizeValues($after);
            $changes = $this->diffChanges($before, $after);

            AuditLog::create([
                'user_id' => auth()->id(),
                'branch_id' => $this->resolveAuditBranch($model),
                'action' => $action,
                'model_type' => get_class($model),
                'model_id' => $model->id,
                'before' => ! empty($changes['old']) ? $changes['old'] : null,
                'after' => ! empty($changes['new']) ? $changes['new'] : null,
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
            ]);
        } catch (\Throwable $e) {
            report($e);
        }
    }

    private function resolveAuditBranch(Model $model): ?int
    {
        $userBranch = auth()->user()?->branch_id;

        if ($userBranch !== null) {
            return $userBranch;
        }

        if (method_exists($model, 'getAttribute')) {
            return $model->getAttribute('branch_id');
        }

        return null;
    }

    private function diffChanges(array $before, array $after): array
    {
        $old = [];
        $new = [];

        foreach ($after as $key => $newValue) {
            $oldValue = $before[$key] ?? null;

            if ($oldValue !== $newValue) {
                $old[$key] = $oldValue;
                $new[$key] = $newValue;
            }
        }

        return ['old' => $old, 'new' => $new];
    }

    private function normalizeValues(array $data): array
    {
        return collect($data)->map(function ($value) {
            // 1. Convert strings that look like dates into Carbon objects
            if (is_string($value) && strtotime($value)) {
                $value = Carbon::parse($value);
            }

            // 2. If we have a Carbon instance, format it based on the time
            if ($value instanceof CarbonInterface) {
                return $value->isStartOfDay()
                    ? $value->toDateString()      // Returns 'YYYY-MM-DD'
                    : $value->toDateTimeString(); // Returns 'YYYY-MM-DD HH:MM:SS'
            }

            return $value;
        })->toArray();
    }
}
