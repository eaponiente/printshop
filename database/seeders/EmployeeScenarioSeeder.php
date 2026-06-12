<?php

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\Payroll\Employee;
use App\Models\Payroll\EmployeeSchedule;
use App\Models\Payroll\OvertimeRequest;
use App\Models\Payroll\Salary;
use App\Models\Payroll\TimeLog;
use App\Models\User;
use Illuminate\Database\Seeder;
use Payroll\Attendance\Enums\PunchSource;
use Payroll\Attendance\Enums\PunchType;
use Payroll\Attendance\Services\AttendanceService;

class EmployeeScenarioSeeder extends Seeder
{
    private AttendanceService $attendanceService;

    private Branch $branch;

    private string $weekStart = '2026-05-25';

    private string $weekEnd = '2026-05-30';

    private float $dailyRate = 510;

    private array $dates = ['2026-05-25', '2026-05-26', '2026-05-27', '2026-05-28', '2026-05-29', '2026-05-30'];

    public function run(): void
    {
        $this->attendanceService = app(AttendanceService::class);
        $this->branch = Branch::firstOrCreate(['name' => 'Babak']);

        $this->seedPerfectA();
        $this->seedPerfectB();
        $this->seedLate();
        $this->seedOvertime();
        $this->seedHalfDay();
    }

    private function createEmployee(string $first, string $last): Employee
    {
        $emp = Employee::firstOrCreate(
            ['first_name' => $first, 'last_name' => $last],
            [
                'branch_id' => $this->branch->id,
                'hire_date' => '2026-01-05',
                'position' => 'regular',
                'status' => 'active',
                'current_daily_rate' => $this->dailyRate,
                'sss_number' => '12-3456789-0',
                'philhealth_number' => '12-345678901-2',
                'pagibig_number' => '1234-5678-9012',
            ]
        );

        if (! Salary::where('employee_id', $emp->id)->exists()) {
            Salary::createForEmployee($emp, $this->dailyRate, '2026-01-05', 'Initial salary');
        }

        $username = strtolower($first);
        User::updateOrCreate(
            ['username' => $username],
            [
                'first_name' => $first,
                'last_name' => $last,
                'password' => bcrypt('password'),
                'role' => 'staff',
                'branch_id' => $this->branch->id,
                'employee_id' => $emp->id,
            ]
        );

        return $emp;
    }

    private function createSchedule(Employee $emp): void
    {
        EmployeeSchedule::firstOrCreate(
            ['employee_id' => $emp->id, 'effective_from' => $this->weekStart],
            [
                'start_time' => '08:00',
                'end_time' => '17:00',
                'rest_days' => [0], // Sunday only
                'effective_to' => null,
                'is_active' => true,
            ]
        );
    }

    private function punch(Employee $emp, string $date, PunchType $type, string $time): void
    {
        TimeLog::firstOrCreate(
            ['employee_id' => $emp->id, 'type' => $type->value, 'timestamp' => "{$date} {$time}"],
            ['source' => PunchSource::SELF_SERVICE]
        );
    }

    private function perfectPunches(Employee $emp, string $date): void
    {
        $this->punch($emp, $date, PunchType::IN, '08:00:00');
        $this->punch($emp, $date, PunchType::LUNCH_OUT, '12:00:00');
        $this->punch($emp, $date, PunchType::LUNCH_IN, '13:00:00');
        $this->punch($emp, $date, PunchType::OUT, '17:00:00');
    }

    private function computeWeek(Employee $emp): void
    {
        foreach ($this->dates as $date) {
            $this->attendanceService->processDailyAttendance($emp, $date);
        }
    }

    // ─── Employee 1: Ana Perfect — all 6 days perfect attendance ───
    private function seedPerfectA(): void
    {
        $emp = $this->createEmployee('Ana', 'Perfect');
        $this->createSchedule($emp);

        foreach ($this->dates as $date) {
            $this->perfectPunches($emp, $date);
        }

        $this->computeWeek($emp);
    }

    // ─── Employee 2: Ben Complete — all 6 days perfect attendance ───
    private function seedPerfectB(): void
    {
        $emp = $this->createEmployee('Ben', 'Complete');
        $this->createSchedule($emp);

        foreach ($this->dates as $date) {
            $this->perfectPunches($emp, $date);
        }

        $this->computeWeek($emp);
    }

    // ─── Employee 3: Carl Late — 1 hour late on Tuesday ───
    private function seedLate(): void
    {
        $emp = $this->createEmployee('Carl', 'Late');
        $this->createSchedule($emp);

        // Mon: Perfect
        $this->perfectPunches($emp, '2026-05-25');

        // Tue: Late 1 hour (IN at 09:00)
        $this->punch($emp, '2026-05-26', PunchType::IN, '09:00:00');
        $this->punch($emp, '2026-05-26', PunchType::LUNCH_OUT, '12:00:00');
        $this->punch($emp, '2026-05-26', PunchType::LUNCH_IN, '13:00:00');
        $this->punch($emp, '2026-05-26', PunchType::OUT, '17:00:00');

        // Wed–Sat: Perfect
        $this->perfectPunches($emp, '2026-05-27');
        $this->perfectPunches($emp, '2026-05-28');
        $this->perfectPunches($emp, '2026-05-29');
        $this->perfectPunches($emp, '2026-05-30');

        $this->computeWeek($emp);
    }

    // ─── Employee 4: Diane OT — 2 hours overtime on Thursday ───
    private function seedOvertime(): void
    {
        $emp = $this->createEmployee('Diane', 'Overtime');
        $this->createSchedule($emp);

        // Mon–Wed: Perfect
        $this->perfectPunches($emp, '2026-05-25');
        $this->perfectPunches($emp, '2026-05-26');
        $this->perfectPunches($emp, '2026-05-27');

        // Thu: 2h OT (OUT at 19:00)
        $this->punch($emp, '2026-05-28', PunchType::IN, '08:00:00');
        $this->punch($emp, '2026-05-28', PunchType::LUNCH_OUT, '12:00:00');
        $this->punch($emp, '2026-05-28', PunchType::LUNCH_IN, '13:00:00');
        $this->punch($emp, '2026-05-28', PunchType::OUT, '19:00:00');

        OvertimeRequest::firstOrCreate(
            ['employee_id' => $emp->id, 'date' => '2026-05-28'],
            [
                'start_time' => '2026-05-28 17:00:00',
                'end_time' => '2026-05-28 19:00:00',
                'shift_type' => 'regular_day',
                'reason' => 'Production deadline',
                'status' => 'approved',
                'approved_by' => 1,
                'approved_at' => now(),
            ]
        );

        // Fri–Sat: Perfect
        $this->perfectPunches($emp, '2026-05-29');
        $this->perfectPunches($emp, '2026-05-30');

        $this->computeWeek($emp);
    }

    // ─── Employee 5: Ella Half — half day on Tuesday (8am–12pm) ───
    private function seedHalfDay(): void
    {
        $emp = $this->createEmployee('Ella', 'HalfDay');
        $this->createSchedule($emp);

        // Mon: Perfect
        $this->perfectPunches($emp, '2026-05-25');

        // Tue: Half day (8am–12pm, no lunch)
        $this->punch($emp, '2026-05-26', PunchType::IN, '08:00:00');
        $this->punch($emp, '2026-05-26', PunchType::OUT, '12:00:00');

        // Wed–Sat: Perfect
        $this->perfectPunches($emp, '2026-05-27');
        $this->perfectPunches($emp, '2026-05-28');
        $this->perfectPunches($emp, '2026-05-29');
        $this->perfectPunches($emp, '2026-05-30');

        $this->computeWeek($emp);
    }
}
