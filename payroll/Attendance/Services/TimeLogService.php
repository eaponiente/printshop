<?php

namespace Payroll\Attendance\Services;

use App\Models\Payroll\AttendanceSheet;
use App\Models\Payroll\Employee;
use App\Models\Payroll\TimeLog;
use App\Models\User;
use Carbon\Carbon;
use DB;
use Illuminate\Validation\ValidationException;
use Payroll\Attendance\Enums\PunchSource;
use Payroll\Attendance\Enums\PunchType;

class TimeLogService
{
    /**
     * Punch types that carry a geolocation. Break punches never do.
     */
    public const GEO_PUNCH_TYPES = ['in', 'out', 'overtime_in', 'overtime_out'];

    /**
     * How long after a punch its coordinates may still be attached.
     */
    private const GEO_ATTACH_WINDOW_MINUTES = 5;

    public function punch(
        Employee $employee,
        PunchType $type,
        User $punchedBy,
        ?float $latitude = null,
        ?float $longitude = null,
        ?int $accuracyMeters = null,
    ): TimeLog {
        return DB::transaction(function () use ($employee, $type, $latitude, $longitude, $accuracyMeters) {
            $now = now();
            $punchDate = $now->toDateString();

            $lockedSheet = AttendanceSheet::where('employee_id', $employee->id)
                ->where('date', $punchDate)
                ->whereNotNull('locked_at')
                ->lockForUpdate()
                ->first();

            if ($lockedSheet) {
                throw ValidationException::withMessages([
                    'error' => 'Attendance sheet for this date is locked in a payroll period.',
                ]);
            }

            $recentDuplicate = TimeLog::where('employee_id', $employee->id)
                ->where('type', $type->value)
                ->where('timestamp', '>=', $now->copy()->subMinutes(5)->toDateTimeString())
                ->whereNull('duplicate_of')
                ->lockForUpdate()
                ->orderBy('timestamp', 'asc')
                ->first();

            $geoData = $this->buildGeoData($employee, $type, $latitude, $longitude, $accuracyMeters);

            if ($recentDuplicate) {
                $log = TimeLog::create([
                    'employee_id' => $employee->id,
                    'type' => $type,
                    'source' => PunchSource::SELF_SERVICE,
                    'timestamp' => $now,
                    'duplicate_of' => $recentDuplicate->id,
                    ...$geoData,
                ]);

                return $log;
            }

            $log = TimeLog::create([
                'employee_id' => $employee->id,
                'type' => $type,
                'source' => PunchSource::SELF_SERVICE,
                'timestamp' => $now,
                ...$geoData,
            ]);

            app(AttendanceService::class)->processDailyAttendance($employee, $punchDate);

            return $log;
        });
    }

    /**
     * Attach coordinates to the employee's most recent geo-eligible punch.
     *
     * A punch is never held back waiting for a GPS fix, so the browser sends
     * the position separately once the device produces one. Only a punch made
     * within the last few minutes that has no coordinates yet is eligible, so
     * a slow or replayed request can never overwrite an earlier punch.
     *
     * Returns null when nothing is eligible — a fix that arrives too late is
     * not an error, it just goes unrecorded.
     */
    public function attachLocationToRecentPunch(
        Employee $employee,
        float $latitude,
        float $longitude,
        ?int $accuracyMeters = null,
    ): ?TimeLog {
        $log = TimeLog::where('employee_id', $employee->id)
            ->whereIn('type', self::GEO_PUNCH_TYPES)
            ->whereNull('latitude')
            ->where('timestamp', '>=', now()->subMinutes(self::GEO_ATTACH_WINDOW_MINUTES)->toDateTimeString())
            ->orderByDesc('timestamp')
            ->first();

        if (! $log) {
            return null;
        }

        $log->update($this->buildGeoData(
            $employee,
            $log->type,
            $latitude,
            $longitude,
            $accuracyMeters,
        ));

        return $log;
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

    /**
     * Was this punch booked before the day it claims to fall on?
     *
     * No legitimate flow does that. A self-service punch stamps `created_at`
     * and `timestamp` at the same instant, and manual logs and corrections are
     * bounded to today or earlier, so both are always created on or after the
     * day they record. A log created *before* its own day got there through a
     * mistyped date, and must never decide what an employee may press: a
     * single stray `in` would otherwise grey out Punch In for that entire day
     * once it arrives, because `can_punch_in` is simply `! $hasIn`.
     *
     * The row is left alone — it still shows on the admin attendance sheet,
     * where it can be removed — it just stops governing the punch pad.
     */
    private function wasBookedBeforeItsOwnDay(TimeLog $log): bool
    {
        return $log->created_at !== null
            && $log->created_at->lessThan($log->timestamp->startOfDay());
    }

    public function punchSequenceForDate(Employee $employee, string $date): array
    {
        $logs = TimeLog::where('employee_id', $employee->id)
            ->whereBetween('timestamp', [$date.' 00:00:00', $date.' 23:59:59'])
            ->whereNull('duplicate_of')
            ->orderBy('timestamp')
            ->get()
            ->reject(fn (TimeLog $log) => $this->wasBookedBeforeItsOwnDay($log))
            ->values();

        $types = $logs->pluck('type.value')->toArray();

        $hasIn = in_array('in', $types);
        $hasOut = in_array('out', $types);
        $hasLunchOut = in_array('lunch_out', $types);
        $hasLunchIn = in_array('lunch_in', $types);
        $hasOvertimeIn = in_array('overtime_in', $types);
        $hasOvertimeOut = in_array('overtime_out', $types);

        $isComplete = $hasOut;

        $lastLog = $logs->last();

        return [
            'logs' => $logs,
            'is_complete' => $isComplete,
            'can_punch_in' => ! $hasIn,
            'can_punch_out' => $hasIn && ! $isComplete && (! $hasLunchOut || $hasLunchIn),
            'can_punch_lunch_out' => $hasIn && ! $isComplete && ! $hasLunchOut,
            'can_punch_lunch_in' => $hasLunchOut && ! $hasLunchIn,
            'can_punch_overtime_in' => $hasOut && ! $hasOvertimeIn,
            'can_punch_overtime_out' => $hasOvertimeIn && ! $hasOvertimeOut,
            'last_punch' => $lastLog ? [
                'type' => $lastLog->type->value,
                'label' => $lastLog->type->label(),
                'timestamp' => $lastLog->timestamp->toIso8601String(),
            ] : null,
        ];
    }

    /**
     * Build geolocation data for a punch. Only stores geo for IN and OUT punches.
     * Computes haversine distance from branch and stores result as note.
     */
    private function buildGeoData(
        Employee $employee,
        PunchType $type,
        ?float $latitude,
        ?float $longitude,
        ?int $accuracyMeters,
    ): array {
        $base = [
            'latitude' => null,
            'longitude' => null,
            'accuracy_meters' => null,
            'note' => null,
        ];

        if (! in_array($type->value, self::GEO_PUNCH_TYPES, true)) {
            return $base;
        }

        if ($latitude === null || $longitude === null) {
            $base['note'] = '📍 Location not provided';

            return $base;
        }

        $base['latitude'] = $latitude;
        $base['longitude'] = $longitude;
        $base['accuracy_meters'] = $accuracyMeters;

        $branch = $employee->branch;

        if (! $branch || $branch->latitude === null || $branch->longitude === null) {
            $base['note'] = '📍 Location recorded. Branch coordinates not set.';

            return $base;
        }

        $distance = $this->haversineDistance(
            $latitude,
            $longitude,
            (float) $branch->latitude,
            (float) $branch->longitude,
        );

        $radius = $branch->geofence_radius ?? 100;

        if ($distance <= $radius) {
            $base['note'] = "📍 {$distance}m from {$branch->name} office ✅";
        } else {
            $base['note'] = "📍 {$distance}m from {$branch->name} office ⚠️";
        }

        return $base;
    }

    /**
     * Haversine formula — distance between two lat/lng points in meters.
     */
    private function haversineDistance(float $lat1, float $lng1, float $lat2, float $lng2): int
    {
        $earthRadius = 6371000; // meters

        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);

        $a = sin($dLat / 2) ** 2
            + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;

        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return (int) round($earthRadius * $c);
    }
}
