<?php

namespace Payroll\Attendance\Services;

use App\Models\Payroll\Employee;
use App\Models\Payroll\TimeLog;
use App\Models\User;
use Carbon\Carbon;
use DB;
use Payroll\Attendance\Enums\PunchSource;
use Payroll\Attendance\Enums\PunchType;

class TimeLogService
{
    public function punch(Employee $employee, PunchType $type, User $punchedBy, ?string $manualTimestamp = null): TimeLog
    {
        return DB::transaction(function () use ($employee, $type, $manualTimestamp) {
            $now = $manualTimestamp ? Carbon::parse($manualTimestamp) : now();

            $recentDuplicate = TimeLog::where('employee_id', $employee->id)
                ->where('type', $type->value)
                ->where('timestamp', '>=', $now->copy()->subMinutes(5)->toDateTimeString())
                ->whereNull('duplicate_of')
                ->lockForUpdate()
                ->orderBy('timestamp', 'asc')
                ->first();

            if ($recentDuplicate) {
                $log = TimeLog::create([
                    'employee_id' => $employee->id,
                    'type' => $type,
                    'source' => PunchSource::SELF_SERVICE,
                    'timestamp' => $now,
                    'duplicate_of' => $recentDuplicate->id,
                ]);

                return $log;
            }

            $log = TimeLog::create([
                'employee_id' => $employee->id,
                'type' => $type,
                'source' => PunchSource::SELF_SERVICE,
                'timestamp' => $now,
            ]);

            app(AttendanceService::class)->processDailyAttendance($employee, $now->toDateString());

            return $log;
        });
    }

    public function manualLog(Employee $employee, array $data): TimeLog
    {
        $log = TimeLog::create([
            'employee_id' => $employee->id,
            'type' => PunchType::from($data['type']),
            'source' => PunchSource::MANUAL,
            'timestamp' => $data['timestamp'],
            'note' => $data['note'] ?? null,
        ]);

        app(AttendanceService::class)->processDailyAttendance(
            $employee,
            Carbon::parse($data['timestamp'])->toDateString()
        );

        return $log;
    }

    public function punchSequenceForDate(Employee $employee, string $date): array
    {
        $logs = TimeLog::where('employee_id', $employee->id)
            ->whereBetween('timestamp', [$date.' 00:00:00', $date.' 23:59:59'])
            ->whereNull('duplicate_of')
            ->orderBy('timestamp')
            ->get();

        $types = $logs->pluck('type.value')->toArray();

        $hasIn = in_array('in', $types);
        $hasOut = in_array('out', $types);
        $hasLunchOut = in_array('lunch_out', $types);
        $hasLunchIn = in_array('lunch_in', $types);

        $isComplete = $hasOut;

        $lastLog = $logs->last();

        return [
            'logs' => $logs,
            'is_complete' => $isComplete,
            'can_punch_in' => ! $hasIn,
            'can_punch_out' => $hasIn && ! $isComplete && (! $hasLunchOut || $hasLunchIn),
            'can_punch_lunch_out' => $hasIn && ! $isComplete && ! $hasLunchOut,
            'can_punch_lunch_in' => $hasLunchOut && ! $hasLunchIn,
            'last_punch' => $lastLog ? [
                'type' => $lastLog->type->value,
                'label' => $lastLog->type->label(),
                'timestamp' => $lastLog->timestamp->toDateTimeString(),
            ] : null,
        ];
    }
}
