<?php

namespace Payroll\Attendance\Services;

use App\Models\Payroll\AttendanceSheet;
use App\Models\Payroll\Holiday;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Payroll\Audit\Traits\Auditable;

class HolidayService
{
    use Auditable;

    public function __construct(private AttendanceService $attendanceService) {}

    public function create(array $data, array $branchIds): Holiday
    {
        return DB::transaction(function () use ($data, $branchIds) {
            $holiday = Holiday::create($data);
            $holiday->branches()->sync($branchIds);

            $this->audit('holiday_created', $holiday, [], [
                ...$holiday->getAttributes(),
                'branch_ids' => $branchIds,
            ]);

            $this->reprocessAffectedSheets($holiday->date->toDateString(), (bool) $holiday->recurring, $branchIds);

            return $holiday;
        });
    }

    public function update(Holiday $holiday, array $data, array $branchIds): Holiday
    {
        return DB::transaction(function () use ($holiday, $data, $branchIds) {
            // Capture the old scope before mutating so we can reprocess the
            // union of old and new — otherwise removing a branch (or moving
            // the date) leaves stale holiday pay on the sheets it no longer
            // applies to.
            $before = $holiday->getAttributes();
            $oldDate = $holiday->date->toDateString();
            $oldRecurring = (bool) $holiday->recurring;
            $oldBranchIds = $holiday->branches->pluck('id')->all();

            $holiday->update($data);
            $holiday->branches()->sync($branchIds);
            $holiday->refresh();

            $this->audit('holiday_updated', $holiday, [
                ...$before,
                'branch_ids' => $oldBranchIds,
            ], [
                ...$holiday->getAttributes(),
                'branch_ids' => $branchIds,
            ]);

            $newDate = $holiday->date->toDateString();
            $newRecurring = (bool) $holiday->recurring;

            $this->reprocessAffectedSheets($oldDate, $oldRecurring, $oldBranchIds);

            $sameScope = $oldDate === $newDate
                && $oldRecurring === $newRecurring
                && $this->sameBranchSet($oldBranchIds, $branchIds);

            if (! $sameScope) {
                $this->reprocessAffectedSheets($newDate, $newRecurring, $branchIds);
            }

            return $holiday;
        });
    }

    public function delete(Holiday $holiday): void
    {
        DB::transaction(function () use ($holiday) {
            $before = $holiday->getAttributes();
            $date = $holiday->date->toDateString();
            $recurring = (bool) $holiday->recurring;
            $branchIds = $holiday->branches->pluck('id')->all();

            $holiday->delete();

            $this->audit('holiday_deleted', $holiday, [
                ...$before,
                'branch_ids' => $branchIds,
            ], []);

            $this->reprocessAffectedSheets($date, $recurring, $branchIds);
        });
    }

    /**
     * Recompute every unlocked attendance sheet that fell within a holiday's
     * scope, so holiday pay reflects the current holiday/branch configuration.
     * Locked sheets (inside a generated period) are immutable and skipped —
     * that also keeps the candidate set naturally bounded to the open period.
     *
     * Date matching mirrors Holiday::forDate()'s two modes and stays
     * SQLite-safe (no MySQL-only date functions).
     */
    protected function reprocessAffectedSheets(string $date, bool $recurring, array $branchIds): void
    {
        $calDate = Carbon::parse($date);

        $query = AttendanceSheet::query()
            ->whereNull('locked_at')                 // locked sheets are immutable
            ->with('employee')                       // no per-row query in the loop
            ->whereIn('employee_id', function ($q) use ($branchIds) {
                $q->select('id')->from('employees');
                if ($branchIds !== []) {             // empty = nationwide = every branch
                    $q->whereIn('branch_id', $branchIds);
                }
            });

        if ($recurring) {
            // Fan-out guard: a recurring holiday matches every year, and
            // whereNull('locked_at') alone doesn't bound that on a system
            // with a long ungenerated backlog — there's no queue here
            // (no app/Jobs, no worker), so an unbounded match would run
            // processDailyAttendance() synchronously for every match in one
            // request. An unlocked sheet older than a year is already an
            // anomaly, so exclude those.
            $query->whereMonth('date', $calDate->month)
                ->whereDay('date', $calDate->day)
                ->where('date', '>=', now()->subYear()->toDateString());
        } else {
            $query->where('date', $date);
        }

        foreach ($query->get() as $sheet) {
            if (! $sheet->employee) {
                continue;
            }

            $this->attendanceService->processDailyAttendance($sheet->employee, $sheet->date->toDateString());
        }
    }

    private function sameBranchSet(array $a, array $b): bool
    {
        sort($a);
        sort($b);

        return $a === $b;
    }
}
