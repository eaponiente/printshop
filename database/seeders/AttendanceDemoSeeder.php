<?php

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\Payroll\Employee;
use App\Models\Payroll\EmployeeSchedule;
use App\Models\Payroll\Holiday;
use App\Models\Payroll\OvertimeRequest;
use App\Models\Payroll\Salary;
use App\Models\Payroll\TimeLog;
use App\Models\User;
use Illuminate\Database\Seeder;
use Payroll\Attendance\Enums\HolidayType;
use Payroll\Attendance\Enums\PunchSource;
use Payroll\Attendance\Enums\PunchType;
use Payroll\Attendance\Services\AttendanceService;

class AttendanceDemoSeeder extends Seeder
{
    private AttendanceService $attendanceService;

    private Branch $branch;

    private string $weekStart = '2026-05-25';

    private string $weekEnd = '2026-05-30';

    private float $dailyRate = 510;

    public function run(): void
    {
        $this->attendanceService = app(AttendanceService::class);
        $this->branch = Branch::firstOrFail();

        $this->seedEmployee1(); // Lates + Absences
        $this->seedEmployee2(); // Holiday + Perfect
        $this->seedEmployee3(); // Overtime
        $this->seedEmployee4(); // Absences + OT + Rest Day Work
    }

    private function createEmployee(string $first, string $last, string $status = 'active'): Employee
    {
        $emp = Employee::firstOrCreate(
            ['first_name' => $first, 'last_name' => $last],
            [
                'branch_id' => $this->branch->id,
                'hire_date' => '2026-01-05',
                'position' => 'regular',
                'status' => $status,
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

    private function createSchedule(Employee $emp, array $restDays = [0, 6], string $start = '08:00', string $end = '17:00'): void
    {
        EmployeeSchedule::firstOrCreate(
            ['employee_id' => $emp->id, 'effective_from' => $this->weekStart],
            [
                'start_time' => $start,
                'end_time' => $end,
                'rest_days' => $restDays,
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

    private function computeWeek(Employee $emp): void
    {
        foreach (['2026-05-25', '2026-05-26', '2026-05-27', '2026-05-28', '2026-05-29', '2026-05-30'] as $date) {
            $this->attendanceService->processDailyAttendance($emp, $date);
        }
    }

    // ─── Employee 1: Juan Late — 2 lates, 1 early leave, 1 absence ───
    private function seedEmployee1(): void
    {
        $emp = $this->createEmployee('Juan', 'Late');
        $this->createSchedule($emp, [0, 6]);

        // Mon: Late 15 min
        $this->punch($emp, '2026-05-25', PunchType::IN, '08:15:00');
        $this->punch($emp, '2026-05-25', PunchType::LUNCH_OUT, '12:00:00');
        $this->punch($emp, '2026-05-25', PunchType::LUNCH_IN, '13:00:00');
        $this->punch($emp, '2026-05-25', PunchType::OUT, '17:00:00');

        // Tue: Late 1 hour
        $this->punch($emp, '2026-05-26', PunchType::IN, '09:00:00');
        $this->punch($emp, '2026-05-26', PunchType::LUNCH_OUT, '12:00:00');
        $this->punch($emp, '2026-05-26', PunchType::LUNCH_IN, '13:00:00');
        $this->punch($emp, '2026-05-26', PunchType::OUT, '17:00:00');

        // Wed: Early leave (3 PM)
        $this->punch($emp, '2026-05-27', PunchType::IN, '08:00:00');
        $this->punch($emp, '2026-05-27', PunchType::LUNCH_OUT, '12:00:00');
        $this->punch($emp, '2026-05-27', PunchType::LUNCH_IN, '13:00:00');
        $this->punch($emp, '2026-05-27', PunchType::OUT, '15:00:00');

        // Thu: Perfect
        $this->punch($emp, '2026-05-28', PunchType::IN, '08:00:00');
        $this->punch($emp, '2026-05-28', PunchType::LUNCH_OUT, '12:00:00');
        $this->punch($emp, '2026-05-28', PunchType::LUNCH_IN, '13:00:00');
        $this->punch($emp, '2026-05-28', PunchType::OUT, '17:00:00');

        // Fri: Absent (no punches)

        // Sat: Rest day

        $this->computeWeek($emp);
    }

    // ─── Employee 2: Maria Perfect — Holiday Wed, rest perfect ───
    private function seedEmployee2(): void
    {
        $emp = $this->createEmployee('Maria', 'Perfect');
        $this->createSchedule($emp, [0, 6]);

        // Create a special holiday on Wednesday
        Holiday::firstOrCreate(
            ['date' => '2026-05-27', 'type' => HolidayType::REGULAR->value],
            ['name' => 'Sample Regular Holiday', 'recurring' => false]
        );

        // Mon: Perfect
        $this->punch($emp, '2026-05-25', PunchType::IN, '08:00:00');
        $this->punch($emp, '2026-05-25', PunchType::LUNCH_OUT, '12:00:00');
        $this->punch($emp, '2026-05-25', PunchType::LUNCH_IN, '13:00:00');
        $this->punch($emp, '2026-05-25', PunchType::OUT, '17:00:00');

        // Tue: Perfect
        $this->punch($emp, '2026-05-26', PunchType::IN, '08:00:00');
        $this->punch($emp, '2026-05-26', PunchType::LUNCH_OUT, '12:00:00');
        $this->punch($emp, '2026-05-26', PunchType::LUNCH_IN, '13:00:00');
        $this->punch($emp, '2026-05-26', PunchType::OUT, '17:00:00');

        // Wed: Holiday — worked (200%)
        $this->punch($emp, '2026-05-27', PunchType::IN, '08:00:00');
        $this->punch($emp, '2026-05-27', PunchType::LUNCH_OUT, '12:00:00');
        $this->punch($emp, '2026-05-27', PunchType::LUNCH_IN, '13:00:00');
        $this->punch($emp, '2026-05-27', PunchType::OUT, '17:00:00');

        // Thu: Perfect
        $this->punch($emp, '2026-05-28', PunchType::IN, '08:00:00');
        $this->punch($emp, '2026-05-28', PunchType::LUNCH_OUT, '12:00:00');
        $this->punch($emp, '2026-05-28', PunchType::LUNCH_IN, '13:00:00');
        $this->punch($emp, '2026-05-28', PunchType::OUT, '17:00:00');

        // Fri: Perfect
        $this->punch($emp, '2026-05-29', PunchType::IN, '08:00:00');
        $this->punch($emp, '2026-05-29', PunchType::LUNCH_OUT, '12:00:00');
        $this->punch($emp, '2026-05-29', PunchType::LUNCH_IN, '13:00:00');
        $this->punch($emp, '2026-05-29', PunchType::OUT, '17:00:00');

        // Sat: Rest day

        $this->computeWeek($emp);
    }

    // ─── Employee 3: Pedro OT — 2 days with overtime ───
    private function seedEmployee3(): void
    {
        $emp = $this->createEmployee('Pedro', 'Overtime');
        $this->createSchedule($emp, [0, 6]);

        // Mon: Full day + 2h OT
        $this->punch($emp, '2026-05-25', PunchType::IN, '08:00:00');
        $this->punch($emp, '2026-05-25', PunchType::LUNCH_OUT, '12:00:00');
        $this->punch($emp, '2026-05-25', PunchType::LUNCH_IN, '13:00:00');
        $this->punch($emp, '2026-05-25', PunchType::OUT, '19:00:00');

        OvertimeRequest::firstOrCreate(
            ['employee_id' => $emp->id, 'date' => '2026-05-25'],
            [
                'hours_needed' => 2,
                'shift_type' => 'regular_day',
                'reason' => 'Deadline rush',
                'status' => 'approved',
                'approved_by' => 1,
                'approved_at' => now(),
            ]
        );

        // Tue: Full day
        $this->punch($emp, '2026-05-26', PunchType::IN, '08:00:00');
        $this->punch($emp, '2026-05-26', PunchType::LUNCH_OUT, '12:00:00');
        $this->punch($emp, '2026-05-26', PunchType::LUNCH_IN, '13:00:00');
        $this->punch($emp, '2026-05-26', PunchType::OUT, '17:00:00');

        // Wed: Full day + 1.5h OT
        $this->punch($emp, '2026-05-27', PunchType::IN, '08:00:00');
        $this->punch($emp, '2026-05-27', PunchType::LUNCH_OUT, '12:00:00');
        $this->punch($emp, '2026-05-27', PunchType::LUNCH_IN, '13:00:00');
        $this->punch($emp, '2026-05-27', PunchType::OUT, '18:30:00');

        OvertimeRequest::firstOrCreate(
            ['employee_id' => $emp->id, 'date' => '2026-05-27'],
            [
                'hours_needed' => 2,
                'shift_type' => 'regular_day',
                'reason' => 'Production backlog',
                'status' => 'approved',
                'approved_by' => 1,
                'approved_at' => now(),
            ]
        );

        // Thu: Perfect
        $this->punch($emp, '2026-05-28', PunchType::IN, '08:00:00');
        $this->punch($emp, '2026-05-28', PunchType::LUNCH_OUT, '12:00:00');
        $this->punch($emp, '2026-05-28', PunchType::LUNCH_IN, '13:00:00');
        $this->punch($emp, '2026-05-28', PunchType::OUT, '17:00:00');

        // Fri: Perfect
        $this->punch($emp, '2026-05-29', PunchType::IN, '08:00:00');
        $this->punch($emp, '2026-05-29', PunchType::LUNCH_OUT, '12:00:00');
        $this->punch($emp, '2026-05-29', PunchType::LUNCH_IN, '13:00:00');
        $this->punch($emp, '2026-05-29', PunchType::OUT, '17:00:00');

        // Sat: Rest day

        $this->computeWeek($emp);
    }

    // ─── Employee 4: Ana Mixed — 2 absences, 1 OT, rest day work ───
    private function seedEmployee4(): void
    {
        $emp = $this->createEmployee('Ana', 'Mixed');
        $this->createSchedule($emp, [0, 6]);

        // Mon: Absent (no punches)

        // Tue: Full day + 3h OT
        $this->punch($emp, '2026-05-26', PunchType::IN, '08:00:00');
        $this->punch($emp, '2026-05-26', PunchType::LUNCH_OUT, '12:00:00');
        $this->punch($emp, '2026-05-26', PunchType::LUNCH_IN, '13:00:00');
        $this->punch($emp, '2026-05-26', PunchType::OUT, '20:00:00');

        OvertimeRequest::firstOrCreate(
            ['employee_id' => $emp->id, 'date' => '2026-05-26'],
            [
                'hours_needed' => 3,
                'shift_type' => 'regular_day',
                'reason' => 'Urgent order',
                'status' => 'approved',
                'approved_by' => 1,
                'approved_at' => now(),
            ]
        );

        // Wed: Perfect
        $this->punch($emp, '2026-05-27', PunchType::IN, '08:00:00');
        $this->punch($emp, '2026-05-27', PunchType::LUNCH_OUT, '12:00:00');
        $this->punch($emp, '2026-05-27', PunchType::LUNCH_IN, '13:00:00');
        $this->punch($emp, '2026-05-27', PunchType::OUT, '17:00:00');

        // Thu: Absent (no punches)

        // Fri: Perfect
        $this->punch($emp, '2026-05-29', PunchType::IN, '08:00:00');
        $this->punch($emp, '2026-05-29', PunchType::LUNCH_OUT, '12:00:00');
        $this->punch($emp, '2026-05-29', PunchType::LUNCH_IN, '13:00:00');
        $this->punch($emp, '2026-05-29', PunchType::OUT, '17:00:00');

        // Sat: Working on rest day (8 hours, 130% rate)
        $this->punch($emp, '2026-05-30', PunchType::IN, '08:00:00');
        $this->punch($emp, '2026-05-30', PunchType::LUNCH_OUT, '12:00:00');
        $this->punch($emp, '2026-05-30', PunchType::LUNCH_IN, '13:00:00');
        $this->punch($emp, '2026-05-30', PunchType::OUT, '17:00:00');

        OvertimeRequest::firstOrCreate(
            ['employee_id' => $emp->id, 'date' => '2026-05-30'],
            [
                'hours_needed' => 8,
                'shift_type' => 'rest_day',
                'reason' => 'Weekend production catch-up',
                'status' => 'approved',
                'approved_by' => 1,
                'approved_at' => now(),
            ]
        );

        $this->computeWeek($emp);
    }
}
