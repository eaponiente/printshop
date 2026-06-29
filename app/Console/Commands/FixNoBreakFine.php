<?php

namespace App\Console\Commands;

use App\Models\Payroll\AttendanceSheet;
use App\Models\Payroll\Fine;
use Illuminate\Console\Command;
use Payroll\Attendance\Services\AttendanceService;

class FixNoBreakFine extends Command
{
    protected $signature = 'attendance:fix-no-break-fine';

    protected $description = 'Reprocess attendance sheets that received a no-break fine while the day was still in progress (no punch-out).';

    public function handle(AttendanceService $service): int
    {
        // Target: sheets with a fine deduction that came purely from the auto no-break logic,
        // i.e. fine_deduction > 0 but no manual Fine record exists for that employee+date.
        $sheets = AttendanceSheet::where('fine_deduction', '>', 0)
            ->whereNotExists(function ($query) {
                $query->selectRaw(1)
                    ->from('fines')
                    ->whereColumn('fines.employee_id', 'attendance_sheets.employee_id')
                    ->whereColumn('fines.date', 'attendance_sheets.date');
            })
            ->orderBy('date')
            ->get(['id', 'employee_id', 'date', 'locked_at']);

        $count = $sheets->count();

        if ($count === 0) {
            $this->info('No affected sheets found.');

            return self::SUCCESS;
        }

        $this->info("Found {$count} sheet(s) to reprocess...");

        $bar = $this->output->createProgressBar($count);
        $done = 0;

        foreach ($sheets as $sheet) {
            $wasLocked = $sheet->locked_at !== null;

            if ($wasLocked) {
                AttendanceSheet::where('id', $sheet->id)->update(['locked_at' => null]);
            }

            $service->processDailyAttendance($sheet->employee, $sheet->date->toDateString());

            if ($wasLocked) {
                AttendanceSheet::where('id', $sheet->id)->update(['locked_at' => now()]);
            }

            $done++;
            $bar->advance();
        }

        $bar->finish();
        $this->newLine();
        $this->info("{$done} sheet(s) reprocessed.");

        return self::SUCCESS;
    }
}
