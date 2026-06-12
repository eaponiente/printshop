<?php

namespace App\Console\Commands;

use App\Models\Branch;
use App\Models\Payroll\Benefit;
use App\Models\Payroll\CashAdvance;
use App\Models\Payroll\Employee;
use App\Models\Payroll\EmployeeSchedule;
use App\Models\Payroll\Holiday;
use App\Models\Payroll\LeaveRequest;
use App\Models\Payroll\OvertimeRequest;
use App\Models\Payroll\PayrollPeriod;
use App\Models\Payroll\Salary;
use App\Models\Payroll\TimeLog;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Payroll\Attendance\Enums\HolidayType;
use Payroll\Attendance\Enums\PunchSource;
use Payroll\Attendance\Enums\PunchType;
use Payroll\Attendance\Services\AttendanceService;
use Payroll\Attendance\Services\PayrollPeriodService;

class GenerateDemoPayrollData extends Command
{
    protected $signature = 'demo:generate-payroll-data
                            {--fresh : Clear existing demo attendance/payroll data first}';

    protected $description = 'Generate demo payroll data with 55 employees, 2 weeks of attendance, and draft payroll periods.';

    private array $branches = [];

    private array $employees = [];

    private array $dates = [];

    private AttendanceService $attendance;

    private PayrollPeriodService $payrollPeriod;

    private array $firstNames = [
        'Juan', 'Maria', 'Jose', 'Ana', 'Pedro', 'Rosa', 'Miguel', 'Sofia',
        'Carlos', 'Elena', 'Antonio', 'Isabel', 'Francisco', 'Teresa', 'Luis',
        'Carmen', 'Ramon', 'Lucia', 'Manuel', 'Beatriz', 'Raul', 'Gloria',
        'Fernando', 'Andrea', 'Alfredo', 'Patricia', 'Roberto', 'Angela',
        'Rico', 'Janice', 'Bernardo', 'Lorna', 'Dante', 'Marcela', 'Edgar',
        'Marlene', 'Victor', 'Cynthia', 'Ronaldo', 'Leticia', 'Eduardo',
        'Miriam', 'Jaime', 'Lorena', 'Emilio', 'Adela', 'Reynaldo', 'Celia',
        'Julius', 'Ruth', 'Arman', 'Kristine', 'Denis', 'Marites',
    ];

    private array $lastNames = [
        'Dela Cruz', 'Santos', 'Reyes', 'Garcia', 'Mendoza', 'Torres',
        'Flores', 'Ramos', 'Castillo', 'Cruz', 'Bautista', 'Villanueva',
        'Gonzales', 'Aquino', 'Rivera', 'Castro', 'Diaz', 'Gutierrez',
        'Salazar', 'Fernandez', 'Morales', 'Valdez', 'Jimenez', 'Pascual',
        'Galang', 'Manuel', 'Domingo', 'Soriano', 'David', 'Lopez',
        'Guevarra', 'Mercado', 'Toledo', 'Abad', 'Velasco', 'Miranda',
        'Caparas', 'Esteban', 'Leonardo', 'Mallari', 'Pagdanganan',
        'Rubio', 'Cayanan', 'Bacani', 'Carreon', 'Gatchalian', 'Salonga',
        'Macapagal', 'Dimaculangan', 'Alejandro', 'Corpuz', 'Ocampo',
        'Bartolome', 'Quinto',
    ];

    public function handle(): int
    {
        $this->components->info('⚙️  Generating demo payroll data...');

        DB::transaction(function () {
            $this->attendance = app(AttendanceService::class);
            $this->payrollPeriod = app(PayrollPeriodService::class);

            $this->components->task('Setting up branches', fn () => $this->createBranches());
            $this->components->task('Setting date range (Jun 8-19)', fn () => $this->createDateRange());
            $this->components->task('Creating Independence Day holiday (Jun 12)', fn () => $this->createHoliday());
            $this->components->task('Creating benefits', fn () => $this->createBenefits());
            $this->components->task('Creating employees (50 new + 5 existing)', fn () => $this->createEmployees());
            $this->components->task('Assigning benefits to employees', fn () => $this->assignBenefits());
            $this->components->task('Creating employee schedules', fn () => $this->createSchedules());
            $this->components->task('Generating time logs', fn () => $this->createTimeLogs());
            $this->components->task('Computing attendance sheets', fn () => $this->computeAttendanceSheets());
            $this->components->task('Creating overtime requests', fn () => $this->createOvertimeRequests());
            $this->components->task('Creating leave requests', fn () => $this->createLeaveRequests());
            $this->components->task('Creating cash advances', fn () => $this->createCashAdvances());
            $this->components->task('Generating payroll periods (draft)', fn () => $this->generatePayrollPeriods());
        });

        $this->components->info('✅ Done! Generated data for '.count($this->employees).' employees across '.count($this->branches).' branches.');
        $this->newLine();
        $this->components->twoColumnDetail('Employees', count($this->employees));
        $this->components->twoColumnDetail('Working Days', count($this->dates).' (Jun 8-19, excl. Sundays)');
        $this->components->twoColumnDetail('Holiday', 'Jun 12 — Independence Day (Regular Holiday)');
        $this->components->twoColumnDetail('Payroll Periods', '2 × Draft');
        $this->components->twoColumnDetail('Overtime Requests', '~15');
        $this->components->twoColumnDetail('Leave Requests', '~12');
        $this->components->twoColumnDetail('Cash Advances', '~10');
        $this->components->twoColumnDetail('Login', 'username = first name (lowercase), password = password');

        return self::SUCCESS;
    }

    private function createBranches(): void
    {
        $names = ['Babak', 'Tibungco', 'Malita', 'Peñaplata'];

        foreach ($names as $name) {
            $this->branches[] = Branch::firstOrCreate(['name' => $name]);
        }
    }

    private function createDateRange(): void
    {
        $start = Carbon::parse('2026-06-08');
        $end = Carbon::parse('2026-06-19');

        for ($date = $start->copy(); $date->lte($end); $date->addDay()) {
            // Exclude Sundays (dayOfWeek = 0 = Sunday)
            if ($date->dayOfWeek === 0) {
                continue;
            }

            $this->dates[] = $date->toDateString();
        }
    }

    private function createHoliday(): void
    {
        Holiday::firstOrCreate(
            ['date' => '2026-06-12'],
            [
                'name' => 'Independence Day',
                'type' => HolidayType::REGULAR,
                'recurring' => true,
            ]
        );
    }

    private function createBenefits(): void
    {
        $benefits = [
            ['name' => 'SSS', 'type' => 'statutory', 'employer_contribution_percent' => 10, 'employee_contribution_percent' => 5, 'employer_contribution_cap' => 2000, 'employee_contribution_cap' => 1000, 'is_active' => true],
            ['name' => 'PhilHealth', 'type' => 'statutory', 'employer_contribution_percent' => 2.5, 'employee_contribution_percent' => 2.5, 'is_active' => true],
            ['name' => 'Pag-IBIG', 'type' => 'statutory', 'is_active' => true],
            ['name' => 'Rice Subsidy', 'type' => 'perk', 'monthly_amount' => 2000, 'is_taxable' => false, 'payslip_label' => 'Rice Subsidy', 'is_active' => true],
            ['name' => 'Laundry Allowance', 'type' => 'perk', 'monthly_amount' => 300, 'is_taxable' => false, 'payslip_label' => 'Laundry Allowance', 'is_active' => true],
        ];

        foreach ($benefits as $b) {
            Benefit::firstOrCreate(['name' => $b['name']], $b);
        }
    }

    private function createEmployees(): void
    {
        // Ensure existing 5 scenario employees are included
        $existingNames = [
            ['Ana', 'Perfect'],
            ['Ben', 'Complete'],
            ['Carl', 'Late'],
            ['Diane', 'Overtime'],
            ['Ella', 'HalfDay'],
        ];

        $existingCount = 0;

        foreach ($existingNames as [$first, $last]) {
            $emp = Employee::firstOrCreate(
                ['first_name' => $first, 'last_name' => $last],
                [
                    'branch_id' => $this->branches[0]->id,
                    'hire_date' => '2026-01-05',
                    'position' => 'regular',
                    'status' => 'active',
                    'current_daily_rate' => 510,
                    'sss_number' => '12-3456789-0',
                    'philhealth_number' => '12-345678901-2',
                    'pagibig_number' => '1234-5678-9012',
                    'tin_number' => '000-000-000-000',
                ]
            );

            if (! Salary::where('employee_id', $emp->id)->exists()) {
                Salary::createForEmployee($emp, 510, '2026-01-05', 'Initial salary');
            }

            $username = strtolower($first);
            User::updateOrCreate(
                ['username' => $username],
                [
                    'first_name' => $first,
                    'last_name' => $last,
                    'password' => bcrypt('password'),
                    'role' => 'staff',
                    'branch_id' => $this->branches[0]->id,
                    'employee_id' => $emp->id,
                ]
            );

            $this->employees[] = $emp;
            $existingCount++;
        }

        // Create 50 new employees
        $usedNames = [];
        foreach ($existingNames as [$first, $last]) {
            $usedNames[$first.' '.$last] = true;
        }

        $created = 0;
        $attempts = 0;
        $maxAttempts = 500;

        while ($created < 50 && $attempts < $maxAttempts) {
            $attempts++;
            $firstName = $this->firstNames[array_rand($this->firstNames)];
            $lastName = $this->lastNames[array_rand($this->lastNames)];
            $key = $firstName.' '.$lastName;

            if (isset($usedNames[$key])) {
                continue;
            }

            $usedNames[$key] = true;

            $branch = $this->branches[array_rand($this->branches)];
            $dailyRate = mt_rand(45, 65) * 10; // 450-650
            $positions = ['regular', 'probation'];
            $position = $positions[array_rand($positions)];
            $roles = ['admin', 'staff'];
            $role = $roles[array_rand($roles)];

            $emp = Employee::create([
                'branch_id' => $branch->id,
                'first_name' => $firstName,
                'last_name' => $lastName,
                'hire_date' => Carbon::create(2025, mt_rand(1, 12), mt_rand(1, 28))->toDateString(),
                'position' => $position,
                'status' => 'active',
                'current_daily_rate' => $dailyRate,
                'sss_number' => '12-'.mt_rand(1000000, 9999999).'-'.mt_rand(0, 9),
                'philhealth_number' => '12-'.mt_rand(100000000, 999999999).'-'.mt_rand(0, 9),
                'pagibig_number' => mt_rand(1000, 9999).'-'.mt_rand(1000, 9999).'-'.mt_rand(1000, 9999),
                'tin_number' => mt_rand(100, 999).'-'.mt_rand(100, 999).'-'.mt_rand(100, 999),
            ]);

            Salary::createForEmployee($emp, $dailyRate, $emp->hire_date->toDateString(), 'Initial salary');

            User::firstOrCreate(
                ['username' => strtolower($firstName).($created + 100)],
                [
                    'first_name' => $firstName,
                    'last_name' => $lastName,
                    'password' => bcrypt('password'),
                    'role' => $role,
                    'branch_id' => $branch->id,
                    'employee_id' => $emp->id,
                ]
            );

            $this->employees[] = $emp;
            $created++;
        }
    }

    private function assignBenefits(): void
    {
        $sss = Benefit::where('name', 'SSS')->first();
        $philhealth = Benefit::where('name', 'PhilHealth')->first();
        $pagibig = Benefit::where('name', 'Pag-IBIG')->first();
        $rice = Benefit::where('name', 'Rice Subsidy')->first();
        $laundry = Benefit::where('name', 'Laundry Allowance')->first();

        foreach ($this->employees as $emp) {
            // All employees get statutory benefits (since they have gov't ID numbers)
            if ($sss && $emp->sss_number) {
                $emp->benefits()->syncWithoutDetaching([$sss->id => [
                    'effective_date' => '2026-01-01',
                    'is_active' => true,
                ]]);
            }

            if ($philhealth && $emp->philhealth_number) {
                $emp->benefits()->syncWithoutDetaching([$philhealth->id => [
                    'effective_date' => '2026-01-01',
                    'is_active' => true,
                ]]);
            }

            if ($pagibig && $emp->pagibig_number) {
                $emp->benefits()->syncWithoutDetaching([$pagibig->id => [
                    'effective_date' => '2026-01-01',
                    'is_active' => true,
                ]]);
            }

            // ~35% get Rice Subsidy
            if ($rice && mt_rand(1, 100) <= 35) {
                $customAmount = mt_rand(1, 2) === 1 ? 2000 : null; // Sometimes use default, sometimes custom
                $emp->benefits()->syncWithoutDetaching([$rice->id => [
                    'effective_date' => '2026-01-01',
                    'custom_monthly_amount' => $customAmount ? $customAmount + (mt_rand(0, 5) * 100) : null,
                    'is_active' => true,
                ]]);
            }

            // ~20% get Laundry Allowance
            if ($laundry && mt_rand(1, 100) <= 20) {
                $emp->benefits()->syncWithoutDetaching([$laundry->id => [
                    'effective_date' => '2026-01-01',
                    'is_active' => true,
                ]]);
            }
        }
    }

    private function createSchedules(): void
    {
        $scheduleVariants = [
            // Rest days: Saturday + Sunday — 9.5h shift with 30min unpaid tail
            ['start_time' => '08:00:00', 'end_time' => '17:30:00', 'unpaid_tail_minutes' => 30, 'rest_days' => [0, 6]],
            // Rest days: Sunday only — 9h shift with no unpaid tail
            ['start_time' => '08:00:00', 'end_time' => '17:00:00', 'unpaid_tail_minutes' => 0, 'rest_days' => [0]],
        ];

        foreach ($this->employees as $emp) {
            $variant = $scheduleVariants[array_rand($scheduleVariants)];

            EmployeeSchedule::firstOrCreate(
                ['employee_id' => $emp->id, 'effective_from' => '2026-06-01'],
                [
                    'start_time' => $variant['start_time'],
                    'end_time' => $variant['end_time'],
                    'unpaid_tail_minutes' => $variant['unpaid_tail_minutes'],
                    'rest_days' => $variant['rest_days'],
                    'effective_to' => null,
                    'is_active' => true,
                ]
            );
        }
    }

    private function createTimeLogs(): void
    {
        $holidayDate = '2026-06-12';

        foreach ($this->employees as $index => $emp) {
            $schedule = EmployeeSchedule::activeForDate($emp->id, '2026-06-08');
            $restDays = $schedule?->rest_days ?? [0, 6];

            // Assign a pattern for this employee (determines attendance variety)
            $pattern = $index % 10;

            foreach ($this->dates as $date) {
                $dayOfWeek = Carbon::parse($date)->dayOfWeek;

                // Skip rest days
                if (in_array($dayOfWeek, $restDays)) {
                    continue;
                }

                $isHoliday = $date === $holidayDate;

                if ($isHoliday) {
                    $this->createHolidayTimeLogs($emp, $date, $pattern);
                } else {
                    $this->createRegularTimeLogs($emp, $date, $pattern);
                }
            }
        }
    }

    private function createRegularTimeLogs(Employee $emp, string $date, int $pattern): void
    {
        $dayOfWeek = Carbon::parse($date)->dayOfWeek;

        // Higher absenteeism on Mondays and Saturdays
        $absentChance = match ($dayOfWeek) {
            1 => 18, // Monday
            6 => 22, // Saturday
            default => 12,
        };

        // Perfect attendance pattern
        if ($pattern === 0 || $pattern === 1) {
            if (mt_rand(1, 100) <= $absentChance) {
                return; // absent
            }
            $this->perfectPunches($emp, $date);

            return;
        }

        // Late pattern (patterns 2-4): sometimes late, otherwise perfect
        if ($pattern >= 2 && $pattern <= 4) {
            if (mt_rand(1, 100) <= $absentChance) {
                return; // absent
            }

            $lateRand = mt_rand(1, 100);
            if ($lateRand <= 30) {
                // Late: 5-45 min
                $lateMin = mt_rand(5, 45);
                $inTime = Carbon::parse("{$date} 08:00")->addMinutes($lateMin)->format('H:i:s');
                $this->createPunch($emp, $date, PunchType::IN, $inTime);
            } elseif ($lateRand <= 40) {
                // Late: 1-2 hours
                $lateMin = mt_rand(60, 120);
                $inTime = Carbon::parse("{$date} 08:00")->addMinutes($lateMin)->format('H:i:s');
                $this->createPunch($emp, $date, PunchType::IN, $inTime);
            } else {
                // On time — but with natural variation
                $this->createPunch($emp, $date, PunchType::IN, $this->randomTime($date, '08:00', -15, 3));
            }

            $this->createPunch($emp, $date, PunchType::LUNCH_OUT, $this->randomTime($date, '12:00', -5, 10));
            $this->createPunch($emp, $date, PunchType::LUNCH_IN, $this->randomTime($date, '13:00', -8, 12));

            // Sometimes leave early
            if (mt_rand(1, 100) <= 8) {
                $earlyOut = mt_rand(1, 45);
                $outTime = Carbon::parse("{$date} 17:00")->subMinutes($earlyOut)->format('H:i:s');
                $this->createPunch($emp, $date, PunchType::OUT, $outTime);
            } else {
                $this->createPunch($emp, $date, PunchType::OUT, $this->randomTime($date, '17:00', -5, 5));
            }

            return;
        }

        // Overtime pattern (patterns 5-6): works overtime 1-2 days
        if ($pattern >= 5 && $pattern <= 6) {
            if (mt_rand(1, 100) <= $absentChance) {
                return; // absent
            }

            $this->createPunch($emp, $date, PunchType::IN, $this->randomTime($date, '08:00', -20, 3));
            $this->createPunch($emp, $date, PunchType::LUNCH_OUT, $this->randomTime($date, '12:00', -5, 10));
            $this->createPunch($emp, $date, PunchType::LUNCH_IN, $this->randomTime($date, '13:00', -8, 12));

            // OT on 1-2 random days
            $willOT = mt_rand(1, 100) <= 20; // 20% chance per day
            if ($willOT) {
                $otHours = mt_rand(1, 3);
                $endHour = 17 + $otHours;
                $endMin = [0, 30][mt_rand(0, 1)];
                $outTime = sprintf('%02d:%02d', $endHour, $endMin);

                $this->createPunch($emp, $date, PunchType::OUT, $outTime);

                $otEndTime = Carbon::parse("{$date} {$outTime}");
                OvertimeRequest::firstOrCreate(
                    ['employee_id' => $emp->id, 'date' => $date],
                    [
                        'start_time' => Carbon::parse("{$date} 17:00:00"),
                        'end_time' => $otEndTime,
                        'shift_type' => 'regular_day',
                        'reason' => $this->randomOTReason(),
                        'status' => 'approved',
                        'approved_by' => 1,
                        'approved_at' => now(),
                    ]
                );
            } else {
                $this->createPunch($emp, $date, PunchType::OUT, $this->randomTime($date, '17:00', -5, 5));
            }

            return;
        }

        // Mixed/half-day pattern (patterns 7-8): occasional absences, half days
        if ($pattern >= 7 && $pattern <= 8) {
            $roll = mt_rand(1, 100);

            if ($roll <= 20) {
                // absent
                return;
            } elseif ($roll <= 35) {
                // Half day: IN at 8:00ish, OUT at 12:00ish
                $this->createPunch($emp, $date, PunchType::IN, $this->randomTime($date, '08:00', -15, 5));
                $this->createPunch($emp, $date, PunchType::OUT, $this->randomTime($date, '12:00', -10, 5));
            } elseif ($roll <= 45) {
                // Late heavily
                $lateMin = mt_rand(60, 180);
                $inTime = Carbon::parse("{$date} 08:00")->addMinutes($lateMin)->format('H:i:s');
                $this->createPunch($emp, $date, PunchType::IN, $inTime);
                $this->createPunch($emp, $date, PunchType::LUNCH_OUT, $this->randomTime($date, '12:00', -5, 15));
                $this->createPunch($emp, $date, PunchType::LUNCH_IN, $this->randomTime($date, '13:00', -10, 10));
                $this->createPunch($emp, $date, PunchType::OUT, $this->randomTime($date, '17:00', -5, 5));
            } else {
                $this->perfectPunches($emp, $date);
            }

            return;
        }

        // Erratic pattern (pattern 9): random combination of all
        $roll = mt_rand(1, 100);

        if ($roll <= 25) {
            return; // absent
        } elseif ($roll <= 40) {
            // Half day morning
            $this->createPunch($emp, $date, PunchType::IN, $this->randomTime($date, '08:00', -15, 5));
            $this->createPunch($emp, $date, PunchType::OUT, $this->randomTime($date, '12:00', -10, 5));
        } elseif ($roll <= 55) {
            // Late + early out
            $lateMin = mt_rand(10, 30);
            $inTime = Carbon::parse("{$date} 08:00")->addMinutes($lateMin)->format('H:i:s');
            $this->createPunch($emp, $date, PunchType::IN, $inTime);
            $this->createPunch($emp, $date, PunchType::LUNCH_OUT, $this->randomTime($date, '12:00', -5, 12));
            $this->createPunch($emp, $date, PunchType::LUNCH_IN, $this->randomTime($date, '13:00', -8, 10));
            $earlyOut = mt_rand(5, 50);
            $outTime = Carbon::parse("{$date} 17:00")->subMinutes($earlyOut)->format('H:i:s');
            $this->createPunch($emp, $date, PunchType::OUT, $outTime);
        } else {
            $this->perfectPunches($emp, $date);
        }
    }

    private function createHolidayTimeLogs(Employee $emp, string $date, int $pattern): void
    {
        $willWork = mt_rand(1, 100) <= 40;

        if ($willWork) {
            $this->createPunch($emp, $date, PunchType::IN, $this->randomTime($date, '08:00', -20, 5));
            $this->createPunch($emp, $date, PunchType::LUNCH_OUT, $this->randomTime($date, '12:00', -5, 10));
            $this->createPunch($emp, $date, PunchType::LUNCH_IN, $this->randomTime($date, '13:00', -10, 10));
            $this->createPunch($emp, $date, PunchType::OUT, $this->randomTime($date, '17:00', -5, 5));

            return;
        }

        // Not working on holiday: no time logs
        // Holiday pay determined by day-before presence (handled by processDailyAttendance)
    }

    private function perfectPunches(Employee $emp, string $date): void
    {
        $this->createPunch($emp, $date, PunchType::IN, $this->randomTime($date, '08:00', -20, 3));
        $this->createPunch($emp, $date, PunchType::LUNCH_OUT, $this->randomTime($date, '12:00', -8, 12));
        $this->createPunch($emp, $date, PunchType::LUNCH_IN, $this->randomTime($date, '13:00', -10, 10));
        $this->createPunch($emp, $date, PunchType::OUT, $this->randomTime($date, '17:00', -8, 5));
    }

    private function createPunch(Employee $emp, string $date, PunchType $type, string $time): void
    {
        TimeLog::create([
            'employee_id' => $emp->id,
            'type' => $type,
            'source' => PunchSource::SELF_SERVICE,
            'timestamp' => Carbon::parse("{$date} {$time}"),
        ]);
    }

    private function randomTime(string $date, string $baseTime, int $minOffset, int $maxOffset): string
    {
        $offset = mt_rand($minOffset, $maxOffset);

        return Carbon::parse("{$date} {$baseTime}")->addMinutes($offset)->format('H:i:s');
    }

    private function computeAttendanceSheets(): void
    {
        $count = 0;
        foreach ($this->employees as $emp) {
            foreach ($this->dates as $date) {
                $this->attendance->processDailyAttendance($emp, $date);
                $count++;
            }
        }

        $this->components->info("   Processed {$count} attendance sheets");
    }

    private function createOvertimeRequests(): void
    {
        // Overtime requests were already created during time log generation for
        // employees with overtime patterns. Additional random OT for some employees.
        $otEmployees = array_filter($this->employees, fn ($_i) => mt_rand(1, 100) <= 30);

        foreach ($otEmployees as $emp) {
            // Pick 1-2 random non-holiday dates for additional OT
            $nonHolidayDates = array_filter($this->dates, fn ($d) => $d !== '2026-06-12');
            shuffle($nonHolidayDates);
            $otDates = array_slice($nonHolidayDates, 0, mt_rand(1, 2));

            foreach ($otDates as $date) {
                $otHours = mt_rand(1, 3);
                $endHour = 17 + $otHours;
                $endTime = Carbon::parse("{$date} {$endHour}:00:00");

                OvertimeRequest::firstOrCreate(
                    ['employee_id' => $emp->id, 'date' => $date],
                    [
                        'start_time' => Carbon::parse("{$date} 17:00:00"),
                        'end_time' => $endTime,
                        'shift_type' => 'regular_day',
                        'reason' => $this->randomOTReason(),
                        'status' => 'approved',
                        'approved_by' => 1,
                        'approved_at' => now(),
                    ]
                );
            }
        }

        $this->components->info('   Created overtime requests');
    }

    private function createLeaveRequests(): void
    {
        $leaveTypes = ['vacation', 'sick', 'emergency', 'bereavement', 'unpaid'];
        $leaveReasons = [
            'vacation' => ['Family vacation', 'Personal trip', 'R&R after busy week'],
            'sick' => ['Not feeling well', 'Doctor appointment', 'Migraine'],
            'emergency' => ['Family emergency', 'House emergency'],
            'bereavement' => ['Funeral attendance', 'Wake attendance'],
            'unpaid' => ['Personal matters', 'Extended leave request'],
        ];

        // Leave requests for ~20% of employees
        $leaveEmployees = array_filter($this->employees, fn ($_i) => mt_rand(1, 100) <= 20);

        foreach ($leaveEmployees as $emp) {
            $nonHolidayDates = array_filter($this->dates, fn ($d) => $d !== '2026-06-12');
            shuffle($nonHolidayDates);
            $leaveDates = array_slice($nonHolidayDates, 0, mt_rand(1, 3));

            foreach ($leaveDates as $date) {
                $leaveType = $leaveTypes[array_rand($leaveTypes)];
                $reasons = $leaveReasons[$leaveType];
                $reason = $reasons[array_rand($reasons)];

                LeaveRequest::firstOrCreate(
                    ['employee_id' => $emp->id, 'date' => $date],
                    [
                        'leave_type' => $leaveType,
                        'duration' => mt_rand(1, 2) === 1 ? 'full' : 'half_am',
                        'is_paid' => $leaveType !== 'unpaid',
                        'reason' => $reason,
                        'status' => 'approved',
                        'approved_by' => 1,
                        'approved_at' => now(),
                    ]
                );
            }
        }

        $this->components->info('   Created leave requests');
    }

    private function createCashAdvances(): void
    {
        // Cash advances for ~15% of employees
        $caEmployees = array_filter($this->employees, fn ($_i) => mt_rand(1, 100) <= 15);

        foreach ($caEmployees as $emp) {
            $amounts = [500, 750, 1000, 1500, 2000, 2500];
            $amount = $amounts[array_rand($amounts)];

            CashAdvance::create([
                'employee_id' => $emp->id,
                'amount' => $amount,
                'remaining_balance' => $amount,
                'reason' => $this->randomCAReason(),
                'status' => 'approved',
                'approved_by' => 1,
                'approved_at' => now(),
            ]);
        }

        $this->components->info('   Created cash advances');
    }

    private function generatePayrollPeriods(): void
    {
        // Group dates by week (Mon-Sat)
        $week1 = array_filter($this->dates, fn ($d) => Carbon::parse($d)->lte(Carbon::parse('2026-06-13')));
        $week2 = array_filter($this->dates, fn ($d) => Carbon::parse($d)->gte(Carbon::parse('2026-06-15')));

        $periods = [];
        if (! empty($week1)) {
            $periods[] = [
                'start' => min($week1),
                'end' => max($week1),
                'label' => 'Week 1 (Jun 8–13)',
            ];
        }
        if (! empty($week2)) {
            $periods[] = [
                'start' => min($week2),
                'end' => max($week2),
                'label' => 'Week 2 (Jun 15–19)',
            ];
        }

        foreach ($periods as $p) {
            foreach ($this->branches as $branch) {
                $existing = PayrollPeriod::where('branch_id', $branch->id)
                    ->where('period_start', $p['start'])
                    ->where('period_end', $p['end'])
                    ->exists();

                if (! $existing) {
                    $this->payrollPeriod->generate($branch, $p['start'], $p['end']);
                }
            }

            $this->components->info("   Generated {$p['label']}");
        }
    }

    private function randomOTReason(): string
    {
        $reasons = [
            'Production deadline',
            'Urgent order fulfillment',
            'Machine maintenance',
            'Inventory stock take',
            'Customer pickup arrangements',
            'Backlog clearance',
        ];

        return $reasons[array_rand($reasons)];
    }

    private function randomCAReason(): string
    {
        $reasons = [
            'Medical expenses',
            'School tuition',
            'Home repair',
            'Emergency travel',
            'Family obligation',
            'Utility bills',
        ];

        return $reasons[array_rand($reasons)];
    }
}
