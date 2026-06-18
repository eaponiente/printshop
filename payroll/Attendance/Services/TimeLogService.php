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
    public function punch(
        Employee $employee,
        PunchType $type,
        User $punchedBy,
        ?float $latitude = null,
        ?float $longitude = null,
        ?int $accuracyMeters = null,
        ?Carbon $timestamp = null,
    ): TimeLog {
        return DB::transaction(function () use ($employee, $type, $latitude, $longitude, $accuracyMeters, $timestamp) {
            $now = $timestamp ?? now();

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

        if (! in_array($type->value, ['in', 'out'], true)) {
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
