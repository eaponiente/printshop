<?php

namespace App\Console\Commands;

use App\Models\Payroll\AttendanceSheet;
use Illuminate\Console\Command;
use Payroll\Attendance\Services\AttendanceService;

class RecalculateLateDeductions extends Command
{
    protected $signature = 'attendance:recalculate-late';

    protected $description = 'Recalculate late deductions for all unlocked attendance sheets.';

    public function handle(AttendanceService $service): int
    {
        $sheets = AttendanceSheet::whereNull('locked_at')
            ->orderBy('date')
            ->get(['id', 'employee_id', 'date']);

        $count = $sheets->count();

        if ($count === 0) {
            $this->info('No unlocked sheets to recalculate.');

            return self::SUCCESS;
        }

        $this->info("Recalculating {$count} unlocked attendance sheets...");

        $bar = $this->output->createProgressBar($count);
        $updated = 0;

        foreach ($sheets as $sheet) {
            $service->processDailyAttendance($sheet->employee, $sheet->date);
            $updated++;
            $bar->advance();
        }

        $bar->finish();
        $this->newLine();
        $this->info("{$updated} sheets recalculated.");

        return self::SUCCESS;
    }
}
