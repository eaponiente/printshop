<?php

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\Payroll\CashAdvance;
use App\Models\Payroll\CorrectionRequest;
use App\Models\Payroll\Employee;
use App\Models\Payroll\EmployeeSchedule;
use App\Models\Payroll\LeaveRequest;
use App\Models\Payroll\OvertimeRequest;
use App\Models\Payroll\Salary;
use App\Models\Payroll\TimeLog;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Payroll\Attendance\Enums\PunchSource;
use Payroll\Attendance\Enums\PunchType;
use Payroll\Attendance\Services\AttendanceService;

class AttendanceFullSeeder extends Seeder
{
    private AttendanceService $attendanceService;

    private Branch $branch;

    private string $startDate = '2026-05-18';

    private string $endDate = '2026-06-01';

    private array $dates = [];

    private array $workDates = [];

    private array $employees = [];

    private array $users = [];

    private array $firstNames = [
        'Rey', 'Angela', 'Mark', 'Catherine', 'Joseph',
        'Bianca', 'Rafael', 'Diana', 'Michael', 'Sofia',
    ];

    private array $lastNames = [
        'Santos', 'Dela Cruz', 'Reyes', 'Gonzales', 'Torres',
        'Mendoza', 'Lopez', 'Rivera', 'Castro', 'Fernandez',
    ];

    public function run(): void
    {
        $this->attendanceService = app(AttendanceService::class);
        $this->branch = Branch::firstOrFail();

        // Build date arrays
        $d = Carbon::parse($this->startDate);
        $end = Carbon::parse($this->endDate);
        while ($d->lte($end)) {
            $ds = $d->toDateString();
            $this->dates[] = $ds;
            if ($d->dayOfWeek !== Carbon::SUNDAY) {
                $this->workDates[] = $ds;
            }
            $d->addDay();
        }

        $this->createEmployees();
        $this->createSchedules();
        $this->generateAttendance();
        $this->generateRequests();
        $this->computeAll();
    }

    private function createEmployees(): void
    {
        for ($i = 0; $i < 10; $i++) {
            $first = $this->firstNames[$i];
            $last = $this->lastNames[$i];
            $rate = match (true) {
                $i < 3 => 500,
                $i < 7 => 550,
                default => 610,
            };

            $emp = Employee::firstOrCreate(
                ['first_name' => $first, 'last_name' => $last],
                [
                    'branch_id' => $this->branch->id,
                    'hire_date' => '2026-01-05',
                    'position' => $i < 2 ? 'regular' : ($i < 6 ? 'contractual' : 'regular'),
                    'status' => 'active',
                    'current_daily_rate' => $rate,
                    'sss_number' => '12-3456789-'.$i,
                    'philhealth_number' => '12-345678901-'.$i,
                    'pagibig_number' => '1234-5678-901'.$i,
                ]
            );

            if (! Salary::where('employee_id', $emp->id)->exists()) {
                Salary::createForEmployee($emp, $rate, '2026-01-05', 'Initial salary');
            }

            $username = strtolower($first);
            $user = User::firstOrCreate(
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

            $this->employees[$emp->id] = $emp;
            $this->users[$emp->id] = $user;
        }
    }

    private function createSchedules(): void
    {
        foreach ($this->employees as $emp) {
            EmployeeSchedule::firstOrCreate(
                ['employee_id' => $emp->id, 'effective_from' => $this->startDate],
                [
                    'start_time' => '08:00',
                    'end_time' => '17:00',
                    'rest_days' => [0, 6], // Sat + Sun off
                    'is_active' => true,
                ]
            );
        }
    }

    private function punch(Employee $emp, string $date, PunchType $type, string $time): void
    {
        TimeLog::firstOrCreate(
            ['employee_id' => $emp->id, 'type' => $type->value, 'timestamp' => "{$date} {$time}"],
            ['source' => PunchSource::SELF_SERVICE]
        );
    }

    private function generateAttendance(): void
    {
        foreach ($this->employees as $eid => $emp) {
            $rng = $eid; // use employee ID as seed for variety

            foreach ($this->workDates as $di => $date) {
                $dayObj = Carbon::parse($date);
                $isSaturday = $dayObj->dayOfWeek === Carbon::SATURDAY;

                // Rest days: no punches on Saturday
                if ($isSaturday) {
                    continue;
                }

                // Random absence: ~8% chance
                if (($rng + $di * 3) % 13 === 0) {
                    continue;
                }

                // Base punch times
                $inHour = 8;
                $inMin = 0;
                $outHour = 17;
                $outMin = 0;

                // Random late: ~25% chance, 5-90 min late
                $late = false;
                if (($rng + $di * 7) % 4 === 0) {
                    $late = true;
                    $lateMins = (($rng * 3 + $di * 11) % 85) + 5;
                    $inMin = $lateMins % 60;
                    $inHour = 8 + intdiv($lateMins, 60);
                }

                // Random early leave: ~15% chance
                $earlyLeave = false;
                if (! $late && ($rng + $di * 5) % 7 === 0) {
                    $earlyLeave = true;
                    $outHour = 15;
                    $outMin = ($rng * $di) % 60;
                }

                $this->punch($emp, $date, PunchType::IN, sprintf('%02d:%02d:00', $inHour, $inMin));
                $this->punch($emp, $date, PunchType::LUNCH_OUT, '12:00:00');
                $this->punch($emp, $date, PunchType::LUNCH_IN, '13:00:00');
                $this->punch($emp, $date, PunchType::OUT, sprintf('%02d:%02d:00', $outHour, $outMin));
            }
        }
    }

    private function generateRequests(): void
    {
        $approverId = User::where('role', 'superadmin')->value('id') ?? 1;

        foreach ($this->employees as $eid => $emp) {
            $days = collect($this->dates)->shuffle($eid);

            // Overtime: employees 2, 4, 7 get OT on 2-3 random days
            if (in_array($eid % 10, [2, 4, 7])) {
                $otDays = $days->take(rand(2, 3));
                foreach ($otDays as $date) {
                    if (Carbon::parse($date)->dayOfWeek === Carbon::SUNDAY) {
                        continue;
                    }
                    $hours = rand(1, 3);
                    OvertimeRequest::firstOrCreate(
                        ['employee_id' => $emp->id, 'date' => $date],
                        [
                            'hours_needed' => $hours,
                            'shift_type' => 'regular_day',
                            'reason' => $this->randomOTReason(),
                            'status' => 'approved',
                            'approved_by' => $approverId,
                            'approved_at' => now(),
                        ]
                    );
                    // Add OT punch
                    $this->punch($emp, $date, PunchType::OUT, sprintf('%02d:00:00', 17 + $hours));
                }
            }

            // Leave: employees 0, 3, 5, 8 get 1-2 leave days
            if (in_array($eid % 10, [0, 3, 5, 8])) {
                $leaveDays = $days->take(rand(1, 2));
                $approved = ($eid % 3 !== 0); // ~66% approved

                foreach ($leaveDays as $date) {
                    if (Carbon::parse($date)->dayOfWeek === Carbon::SUNDAY) {
                        continue;
                    }
                    $types = ['vacation', 'sick', 'emergency'];
                    LeaveRequest::firstOrCreate(
                        ['employee_id' => $emp->id, 'date' => $date],
                        [
                            'leave_type' => $types[array_rand($types)],
                            'duration' => rand(0, 1) ? 'full_day' : 'half_day_morning',
                            'is_paid' => true,
                            'reason' => $this->randomLeaveReason(),
                            'status' => $approved ? 'approved' : 'denied',
                            'approved_by' => $approved ? $approverId : null,
                            'approved_at' => $approved ? now() : null,
                        ]
                    );
                }
            }

            // Corrections: employees 1, 3, 6, 9 get 1 correction
            if (in_array($eid % 10, [1, 3, 6, 9])) {
                $corrDay = $days->first();
                if ($corrDay && Carbon::parse($corrDay)->dayOfWeek !== Carbon::SUNDAY) {
                    $types = ['missed_punch_in', 'missed_punch_out', 'time_adjustment'];
                    CorrectionRequest::firstOrCreate(
                        ['employee_id' => $emp->id, 'date' => $corrDay, 'correction_type' => $types[array_rand($types)]],
                        [
                            'requested_time' => $corrDay.' 08:00:00',
                            'reason' => 'Forgot to punch',
                            'status' => ($eid % 4 === 0) ? 'denied' : 'approved',
                            'reviewed_by' => ($eid % 4 === 0) ? $approverId : null,
                            'reviewed_at' => now(),
                            'denial_reason' => ($eid % 4 === 0) ? 'Insufficient evidence' : null,
                        ]
                    );
                }
            }

            // Cash Advances: employees 2, 5, 7, 9 get 1 CA
            if (in_array($eid % 10, [2, 5, 7, 9])) {
                $amount = rand(1, 5) * 500;
                CashAdvance::firstOrCreate(
                    ['employee_id' => $emp->id, 'amount' => $amount],
                    [
                        'remaining_balance' => ($eid % 3 === 0) ? 0 : $amount,
                        'reason' => $this->randomCAReason(),
                        'status' => ($eid % 3 === 0) ? 'paid' : (($eid % 2 === 0) ? 'approved' : 'pending'),
                        'approved_by' => ($eid % 3 !== 0) ? $approverId : null,
                        'approved_at' => ($eid % 3 !== 0) ? now() : null,
                    ]
                );
            }
        }
    }

    private function computeAll(): void
    {
        foreach ($this->employees as $emp) {
            foreach ($this->dates as $date) {
                if (Carbon::parse($date)->dayOfWeek === Carbon::SUNDAY) {
                    continue;
                }
                $this->attendanceService->processDailyAttendance($emp, $date);
            }
        }
    }

    private function randomOTReason(): string
    {
        $reasons = ['Deadline rush', 'Production backlog', 'Urgent order', 'Client deadline', 'Inventory count'];

        return $reasons[array_rand($reasons)];
    }

    private function randomLeaveReason(): string
    {
        $reasons = ['Family emergency', 'Medical appointment', 'Personal errand', 'Feeling unwell', 'Rest day request'];

        return $reasons[array_rand($reasons)];
    }

    private function randomCAReason(): string
    {
        $reasons = ['Medical expense', 'Tuition fee', 'Emergency funds', 'Transportation', 'Family support'];

        return $reasons[array_rand($reasons)];
    }
}
