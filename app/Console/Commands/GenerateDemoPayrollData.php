<?php

namespace App\Console\Commands;

use App\Models\Branch;
use App\Models\Payroll\AttendanceSheet;
use App\Models\Payroll\Benefit;
use App\Models\Payroll\CashAdvance;
use App\Models\Payroll\Employee;
use App\Models\Payroll\EmployeeSchedule;
use App\Models\Payroll\Fine;
use App\Models\Payroll\Holiday;
use App\Models\Payroll\LeaveRequest;
use App\Models\Payroll\OvertimeRequest;
use App\Models\Payroll\PayrollPeriod;
use App\Models\Payroll\PayrollPeriodItem;
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
                            {--fresh : Clear existing demo data for these branches before generating}';

    protected $description = 'Generate demo payroll data with 55 employees, 2 weeks of attendance, and draft payroll periods.';

    private array $branches = [];
    private array $employees = [];
    private array $dates = [];
    private AttendanceService $attendance;
    private PayrollPeriodService $payrollPeriod;
    private ?int $approverId = null;

    // Per-employee scenario plans, keyed by employee ID
    private array $employeePatterns = [];
    private array $empOTDates = [];        // empId => [date => 'HH:MM:SS' out time]
    private array $empFullDayLeaves = [];  // empId => [date => [type, isPaid, reason]]
    private array $empHalfDayDates = [];   // empId => [date => 'am']
    private array $empRestDayWork = [];    // empId => [date, ...]
    private array $empFines = [];          // empId => [[date, type, amount, note], ...]

    private const DEMO_BRANCHES = ['Babak', 'Tibungco', 'Malita', 'Peñaplata'];

    private array $firstNames = [
        'Juan', 'Maria', 'Jose', 'Pedro', 'Rosa', 'Miguel', 'Sofia',
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

    // ─── Entry point ─────────────────────────────────────────────────────────

    public function handle(): int
    {
        if (app()->isProduction()) {
            $this->error('This command cannot be run in production.');

            return self::FAILURE;
        }

        if ($this->option('fresh')) {
            $this->components->info('🗑  Clearing existing demo data...');
            $this->clearDemoData();
        }

        $this->components->info('⚙️  Generating demo payroll data...');

        DB::transaction(function () {
            $this->attendance = app(AttendanceService::class);
            $this->payrollPeriod = app(PayrollPeriodService::class);

            $this->components->task('Setting up branches', fn () => $this->createBranches());
            $this->components->task('Setting date range (Jun 8–19)', fn () => $this->createDateRange());
            $this->components->task('Creating Independence Day holiday (Jun 12)', fn () => $this->createHoliday());
            $this->components->task('Creating benefits', fn () => $this->createBenefits());
            $this->components->task('Creating employees (5 scenario + 50 random)', fn () => $this->createEmployees());
            $this->components->task('Resolving approver user', fn () => $this->resolveApprover());
            $this->components->task('Creating employee schedules', fn () => $this->createSchedules());
            $this->components->task('Planning attendance scenarios', fn () => $this->planScenarios());
            // Prerequisite data must exist before attendance computation
            $this->components->task('Generating time logs', fn () => $this->createTimeLogs());
            $this->components->task('Creating leave requests', fn () => $this->createLeaveRequests());
            $this->components->task('Creating fines', fn () => $this->createFines());
            $this->components->task('Assigning benefits to employees', fn () => $this->assignBenefits());
            $this->components->task('Computing attendance sheets', fn () => $this->computeAttendanceSheets());
            $this->components->task('Creating cash advances', fn () => $this->createCashAdvances());
            $this->components->task('Generating payroll periods (draft)', fn () => $this->generatePayrollPeriods());
        });

        $otCount = array_sum(array_map('count', $this->empOTDates));
        $leaveCount = array_sum(array_map('count', $this->empFullDayLeaves));
        $halfDayCount = array_sum(array_map('count', $this->empHalfDayDates));
        $fineCount = array_sum(array_map('count', $this->empFines));

        $this->newLine();
        $this->components->info('✅ Done!');
        $this->newLine();
        $this->components->twoColumnDetail('Employees', count($this->employees));
        $this->components->twoColumnDetail('Branches', count($this->branches));
        $this->components->twoColumnDetail('Working Days', count($this->dates).' (Jun 8–19, excl. Sundays)');
        $this->components->twoColumnDetail('Holiday', 'Jun 12 — Independence Day (Regular)');
        $this->components->twoColumnDetail('Payroll Periods', '2 × Draft');
        $this->components->twoColumnDetail('OT Days (punch-based)', $otCount);
        $this->components->twoColumnDetail('Full-Day Leaves', $leaveCount);
        $this->components->twoColumnDetail('Half-Day Events', $halfDayCount);
        $this->components->twoColumnDetail('Fines Issued', $fineCount);
        $this->components->twoColumnDetail('Login', 'username = first name (lowercase), password = password');

        return self::SUCCESS;
    }

    // ─── Fresh cleanup ────────────────────────────────────────────────────────

    private function clearDemoData(): void
    {
        $branches = Branch::whereIn('name', self::DEMO_BRANCHES)->get();
        if ($branches->isEmpty()) {
            return;
        }

        $branchIds = $branches->pluck('id')->toArray();
        $empIds = Employee::whereIn('branch_id', $branchIds)->pluck('id')->toArray();

        $periodIds = PayrollPeriod::whereIn('branch_id', $branchIds)->pluck('id')->toArray();
        PayrollPeriodItem::whereIn('payroll_period_id', $periodIds)->delete();
        PayrollPeriod::whereIn('branch_id', $branchIds)->delete();

        AttendanceSheet::whereIn('employee_id', $empIds)->delete();
        TimeLog::whereIn('employee_id', $empIds)->delete();
        OvertimeRequest::whereIn('employee_id', $empIds)->delete();
        LeaveRequest::whereIn('employee_id', $empIds)->delete();
        Fine::whereIn('employee_id', $empIds)->delete();
        CashAdvance::whereIn('employee_id', $empIds)->delete();

        DB::table('benefit_employee')->whereIn('employee_id', $empIds)->delete();
        Salary::whereIn('employee_id', $empIds)->delete();
        EmployeeSchedule::whereIn('employee_id', $empIds)->delete();

        User::whereIn('employee_id', $empIds)->where('role', '!=', 'superadmin')->delete();
        Employee::whereIn('id', $empIds)->delete();
        Branch::whereIn('id', $branchIds)->delete();
    }

    // ─── Setup ───────────────────────────────────────────────────────────────

    private function createBranches(): void
    {
        foreach (self::DEMO_BRANCHES as $name) {
            $this->branches[] = Branch::firstOrCreate(['name' => $name]);
        }
    }

    private function createDateRange(): void
    {
        $start = Carbon::parse('2026-06-08');
        $end = Carbon::parse('2026-06-19');
        for ($d = $start->copy(); $d->lte($end); $d->addDay()) {
            if ($d->dayOfWeek !== Carbon::SUNDAY) {
                $this->dates[] = $d->toDateString();
            }
        }
    }

    private function createHoliday(): void
    {
        Holiday::firstOrCreate(
            ['date' => '2026-06-12'],
            ['name' => 'Independence Day', 'type' => HolidayType::REGULAR, 'recurring' => true]
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
        // 5 named scenario employees in branch[0] for predictable demo data
        $scenarios = [
            ['Ana', 'Perfect'],
            ['Ben', 'Complete'],
            ['Carl', 'Late'],
            ['Diane', 'Overtime'],
            ['Ella', 'HalfDay'],
        ];
        $usedNames = [];
        foreach ($scenarios as [$first, $last]) {
            $usedNames["{$first} {$last}"] = true;
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
            User::updateOrCreate(
                ['username' => strtolower($first)],
                [
                    'first_name' => $first, 'last_name' => $last,
                    'password' => bcrypt('password'),
                    'role' => 'staff',
                    'branch_id' => $this->branches[0]->id,
                    'employee_id' => $emp->id,
                ]
            );
            $this->employees[] = $emp;
        }

        // 50 random employees spread across all branches
        $created = 0;
        $attempts = 0;
        while ($created < 50 && $attempts < 500) {
            $attempts++;
            $first = $this->firstNames[array_rand($this->firstNames)];
            $last = $this->lastNames[array_rand($this->lastNames)];
            if (isset($usedNames["{$first} {$last}"])) {
                continue;
            }
            $usedNames["{$first} {$last}"] = true;

            $branch = $this->branches[array_rand($this->branches)];
            $dailyRate = mt_rand(45, 65) * 10;

            $emp = Employee::create([
                'branch_id' => $branch->id,
                'first_name' => $first,
                'last_name' => $last,
                'hire_date' => Carbon::create(2025, mt_rand(1, 12), mt_rand(1, 28))->toDateString(),
                'position' => ['regular', 'probation'][mt_rand(0, 1)],
                'status' => 'active',
                'current_daily_rate' => $dailyRate,
                'sss_number' => '12-'.mt_rand(1000000, 9999999).'-'.mt_rand(0, 9),
                'philhealth_number' => '12-'.mt_rand(100000000, 999999999).'-'.mt_rand(0, 9),
                'pagibig_number' => mt_rand(1000, 9999).'-'.mt_rand(1000, 9999).'-'.mt_rand(1000, 9999),
                'tin_number' => mt_rand(100, 999).'-'.mt_rand(100, 999).'-'.mt_rand(100, 999),
            ]);
            Salary::createForEmployee($emp, $dailyRate, $emp->hire_date->toDateString(), 'Initial salary');
            User::firstOrCreate(
                ['username' => strtolower($first).($created + 100)],
                [
                    'first_name' => $first, 'last_name' => $last,
                    'password' => bcrypt('password'),
                    'role' => ['admin', 'staff'][mt_rand(0, 1)],
                    'branch_id' => $branch->id,
                    'employee_id' => $emp->id,
                ]
            );
            $this->employees[] = $emp;
            $created++;
        }
    }

    private function resolveApprover(): void
    {
        $branchIds = collect($this->branches)->pluck('id')->toArray();
        $user = User::whereIn('branch_id', $branchIds)->where('role', 'admin')->first()
            ?? User::whereIn('branch_id', $branchIds)->first();
        $this->approverId = $user?->id ?? 1;
    }

    private function createSchedules(): void
    {
        $variants = [
            // Sun+Sat rest: 9.5h shift, 30min unpaid tail → paid end 17:00, OT threshold 510 work-min
            ['start_time' => '08:00:00', 'end_time' => '17:30:00', 'unpaid_tail_minutes' => 30, 'rest_days' => [0, 6]],
            // Sun-only rest: 9h shift, 0min tail → paid end 17:00, OT threshold 480 work-min
            ['start_time' => '08:00:00', 'end_time' => '17:00:00', 'unpaid_tail_minutes' => 0, 'rest_days' => [0]],
        ];

        foreach ($this->employees as $emp) {
            $v = $variants[array_rand($variants)];
            EmployeeSchedule::firstOrCreate(
                ['employee_id' => $emp->id, 'effective_from' => '2026-06-01'],
                array_merge($v, ['effective_to' => null, 'is_active' => true])
            );
        }
    }

    // ─── Scenario planning ────────────────────────────────────────────────────

    private function planScenarios(): void
    {
        $patterns = $this->buildPatternQueue();

        // Non-holiday weekdays available for leaves, OT, fines
        $weekdays = array_values(array_filter(
            $this->dates,
            fn ($d) => $d !== '2026-06-12' && Carbon::parse($d)->dayOfWeek !== Carbon::SATURDAY
        ));

        $saturdays = array_values(array_filter(
            $this->dates,
            fn ($d) => Carbon::parse($d)->dayOfWeek === Carbon::SATURDAY
        ));

        foreach ($this->employees as $i => $emp) {
            $pattern = $patterns[$i] ?? 'mostly_on_time';
            $this->employeePatterns[$emp->id] = $pattern;
            $empId = $emp->id;

            $schedule = EmployeeSchedule::where('employee_id', $empId)->first();
            $schedEnd = $schedule?->end_time
                ? Carbon::parse($schedule->end_time)->format('H:i')
                : '17:00';
            $hasSaturdayRest = in_array(6, $schedule?->rest_days ?? []);

            match ($pattern) {
                'overtime'       => $this->planOT($empId, $weekdays, $schedEnd, 3),
                'overtime_heavy' => $this->planOT($empId, $weekdays, $schedEnd, 5),
                'half_day'       => $this->planHalfDayEmployee($empId, $weekdays),
                'leave_heavy'    => $this->planLeaves($empId, $weekdays, 3, 4),
                'fined'          => $this->planFine($empId, $weekdays, 2, 3),
                'rest_day_worker'=> $this->planRestDayWork($empId, $saturdays, $hasSaturdayRest),
                default          => null,
            };

            // Sprinkle extras across all employees
            if (mt_rand(1, 100) <= 25 && ! isset($this->empFullDayLeaves[$empId])) {
                $this->planLeaves($empId, $weekdays, 1, 2);
            }
            if (mt_rand(1, 100) <= 12 && ! isset($this->empFines[$empId])) {
                $this->planFine($empId, $weekdays, 1, 1);
            }
        }
    }

    /** Returns pattern assignment for all 55 employees in order. */
    private function buildPatternQueue(): array
    {
        // Fixed scenario employees 0-4
        $queue = ['perfect', 'mostly_on_time', 'chronic_late', 'overtime', 'half_day'];

        // 50 random employees with realistic distribution
        $pool = [
            ...array_fill(0, 14, 'mostly_on_time'),
            ...array_fill(0, 10, 'chronic_late'),
            ...array_fill(0, 7, 'overtime'),
            ...array_fill(0, 5, 'overtime_heavy'),
            ...array_fill(0, 5, 'erratic'),
            ...array_fill(0, 3, 'high_absent'),
            ...array_fill(0, 3, 'leave_heavy'),
            ...array_fill(0, 2, 'fined'),
            ...array_fill(0, 1, 'rest_day_worker'),
        ];
        shuffle($pool);

        return array_merge($queue, $pool);
    }

    private function planOT(int $empId, array $weekdays, string $schedEnd, int $count): void
    {
        if (empty($weekdays)) {
            return;
        }
        $shuffled = $weekdays;
        shuffle($shuffled);
        $endHour = (int) explode(':', $schedEnd)[0];

        foreach (array_slice($shuffled, 0, min($count, count($shuffled))) as $date) {
            // Punch out 2–3h after schedule end to guarantee ≥60 min of actual OT
            $otHours = mt_rand(2, 3);
            $otMins = [0, 30][mt_rand(0, 1)];
            $this->empOTDates[$empId][$date] = sprintf('%02d:%02d:00', $endHour + $otHours, $otMins);
        }
    }

    private function planHalfDayEmployee(int $empId, array $weekdays): void
    {
        if (empty($weekdays)) {
            return;
        }
        $shuffled = $weekdays;
        shuffle($shuffled);

        // 2 full-day paid leaves
        foreach (array_slice($shuffled, 0, 2) as $date) {
            $this->empFullDayLeaves[$empId][$date] = [
                'type' => ['vacation', 'sick'][mt_rand(0, 1)],
                'isPaid' => true,
                'reason' => ['Family vacation', 'Not feeling well'][mt_rand(0, 1)],
            ];
        }

        // 3 half-day mornings on remaining dates
        $remaining = array_values(array_diff($shuffled, array_keys($this->empFullDayLeaves[$empId] ?? [])));
        foreach (array_slice($remaining, 0, 3) as $date) {
            $this->empHalfDayDates[$empId][$date] = 'am';
        }
    }

    private function planLeaves(int $empId, array $weekdays, int $min, int $max): void
    {
        if (empty($weekdays)) {
            return;
        }
        $shuffled = $weekdays;
        shuffle($shuffled);
        $count = mt_rand($min, min($max, count($shuffled)));
        $leaveMap = ['vacation' => 'Family vacation', 'sick' => 'Medical appointment', 'emergency' => 'Family emergency', 'unpaid' => 'Personal matters'];

        foreach (array_slice($shuffled, 0, $count) as $date) {
            if (! isset($this->empFullDayLeaves[$empId][$date])) {
                $type = array_rand($leaveMap);
                $this->empFullDayLeaves[$empId][$date] = [
                    'type' => $type,
                    'isPaid' => $type !== 'unpaid',
                    'reason' => $leaveMap[$type],
                ];
            }
        }
    }

    private function planFine(int $empId, array $weekdays, int $min, int $max): void
    {
        if (empty($weekdays)) {
            return;
        }
        $shuffled = $weekdays;
        shuffle($shuffled);
        $count = mt_rand($min, min($max, count($shuffled)));
        $templates = [
            ['type' => 'tardiness', 'amount' => 100, 'note' => 'Repeated tardiness'],
            ['type' => 'misconduct', 'amount' => 300, 'note' => 'Unprofessional behavior'],
            ['type' => 'negligence', 'amount' => 200, 'note' => 'Negligent handling of equipment'],
            ['type' => 'tardiness', 'amount' => 150, 'note' => 'Late more than 30 minutes without notice'],
        ];

        foreach (array_slice($shuffled, 0, $count) as $date) {
            $t = $templates[array_rand($templates)];
            $this->empFines[$empId][] = ['date' => $date, ...$t];
        }
    }

    private function planRestDayWork(int $empId, array $saturdays, bool $hasSaturdayRest): void
    {
        // Only meaningful when Saturday is a rest day; demonstrates 1.30x rest-day pay
        if (! $hasSaturdayRest || empty($saturdays)) {
            return;
        }
        $this->empRestDayWork[$empId] = array_slice($saturdays, 0, min(2, count($saturdays)));
    }

    // ─── Data creation (all run before computeAttendanceSheets) ─────────────

    private function createTimeLogs(): void
    {
        $holidayDate = '2026-06-12';

        foreach ($this->employees as $emp) {
            $empId = $emp->id;
            $schedule = EmployeeSchedule::activeForDate($empId, '2026-06-08');
            $restDays = $schedule?->rest_days ?? [0, 6];
            $pattern = $this->employeePatterns[$empId] ?? 'mostly_on_time';
            $restDayWorkDates = $this->empRestDayWork[$empId] ?? [];

            foreach ($this->dates as $date) {
                $dow = Carbon::parse($date)->dayOfWeek;
                $isRestDay = in_array($dow, $restDays);

                // Skip rest days unless this employee is planned to work it
                if ($isRestDay && ! in_array($date, $restDayWorkDates)) {
                    continue;
                }

                // Full-day leave: no punches — leave request drives attendance
                if (isset($this->empFullDayLeaves[$empId][$date])) {
                    continue;
                }

                if ($date === $holidayDate) {
                    $this->createHolidayTimeLogs($emp, $date, $pattern);
                    continue;
                }

                // Rest-day work (Sat for Sun+Sat-off employees): clean full day
                if (in_array($date, $restDayWorkDates)) {
                    $this->perfectPunches($emp, $date);
                    continue;
                }

                // Half-day morning: punch in, out at noon
                if (isset($this->empHalfDayDates[$empId][$date])) {
                    $this->createPunch($emp, $date, PunchType::IN, $this->randomTime($date, '08:00', -10, 5));
                    $this->createPunch($emp, $date, PunchType::OUT, $this->randomTime($date, '12:00', -5, 10));
                    continue;
                }

                // OT day: regular punches out at schedule end, then OVERTIME_IN/OVERTIME_OUT
                // as the authorization window. The service uses OT punches to approve OT pay.
                if (isset($this->empOTDates[$empId][$date])) {
                    $otOutTime = $this->empOTDates[$empId][$date];
                    $schedEnd = $schedule?->end_time
                        ? Carbon::parse($schedule->end_time)->format('H:i:s')
                        : '17:00:00';

                    $this->createPunch($emp, $date, PunchType::IN, $this->randomTime($date, '08:00', -20, 3));
                    $this->createPunch($emp, $date, PunchType::LUNCH_OUT, $this->randomTime($date, '12:00', -5, 8));
                    $this->createPunch($emp, $date, PunchType::LUNCH_IN, $this->randomTime($date, '13:00', -5, 10));
                    $this->createPunch($emp, $date, PunchType::OUT, $otOutTime);
                    $this->createPunch($emp, $date, PunchType::OVERTIME_IN, $schedEnd);
                    $this->createPunch($emp, $date, PunchType::OVERTIME_OUT, $otOutTime);
                    continue;
                }

                // Regular day by pattern
                $this->createRegularTimeLogs($emp, $date, $pattern);
            }
        }
    }

    private function createRegularTimeLogs(Employee $emp, string $date, string $pattern): void
    {
        $dow = Carbon::parse($date)->dayOfWeek;
        // Higher absence on Mondays and Saturdays
        $absentChance = match ($dow) {
            Carbon::MONDAY   => 15,
            Carbon::SATURDAY => 20,
            default          => 8,
        };

        match ($pattern) {
            'perfect'                    => $this->perfectPunches($emp, $date),
            'mostly_on_time',
            'overtime', 'overtime_heavy',
            'leave_heavy', 'fined',
            'rest_day_worker'            => $this->mostlyOnTimePunches($emp, $date, $absentChance),
            'chronic_late'               => $this->chronicLatePunches($emp, $date, $absentChance),
            'erratic'                    => $this->erraticPunches($emp, $date),
            'high_absent'                => $this->highAbsentPunches($emp, $date),
            'half_day'                   => $this->mostlyOnTimePunches($emp, $date, $absentChance),
            default                      => $this->mostlyOnTimePunches($emp, $date, $absentChance),
        };
    }

    private function createHolidayTimeLogs(Employee $emp, string $date, string $pattern): void
    {
        // Overtime and perfect employees more likely to show up on holidays
        $workChance = in_array($pattern, ['perfect', 'overtime', 'overtime_heavy']) ? 60 : 35;
        if (mt_rand(1, 100) <= $workChance) {
            $this->perfectPunches($emp, $date);
        }
        // Absent on holiday: no punches; service records 0 holiday pay per business rules
    }

    // ─── Punch patterns ───────────────────────────────────────────────────────

    private function perfectPunches(Employee $emp, string $date): void
    {
        $this->createPunch($emp, $date, PunchType::IN, $this->randomTime($date, '08:00', -20, 0));
        // Lunch out at or after noon, lunch in at or before 13:00 → lunch ≤ 60 min, no deduction
        $this->createPunch($emp, $date, PunchType::LUNCH_OUT, $this->randomTime($date, '12:00', 0, 5));
        $this->createPunch($emp, $date, PunchType::LUNCH_IN, $this->randomTime($date, '13:00', -5, 0));
        $this->createPunch($emp, $date, PunchType::OUT, $this->randomTime($date, '17:00', 0, 15));
    }

    private function mostlyOnTimePunches(Employee $emp, string $date, int $absentChance): void
    {
        if (mt_rand(1, 100) <= $absentChance) {
            return;
        }

        // 10% minor late (1–10 min), otherwise early/on-time
        $inTime = mt_rand(1, 100) <= 10
            ? $this->randomTime($date, '08:00', 1, 10)
            : $this->randomTime($date, '08:00', -20, 3);

        $this->createPunch($emp, $date, PunchType::IN, $inTime);
        // Lunch out 0–10 min after noon, lunch in 0–10 min before 1 pm → max 60 min lunch
        $this->createPunch($emp, $date, PunchType::LUNCH_OUT, $this->randomTime($date, '12:00', 0, 10));
        $this->createPunch($emp, $date, PunchType::LUNCH_IN, $this->randomTime($date, '13:00', -10, 0));

        // 6% genuine undertime (leave 5–20 min early); otherwise at/after 17:00
        $outTime = mt_rand(1, 100) <= 6
            ? $this->randomTime($date, '17:00', -20, -5)
            : $this->randomTime($date, '17:00', 0, 15);

        $this->createPunch($emp, $date, PunchType::OUT, $outTime);
    }

    private function chronicLatePunches(Employee $emp, string $date, int $absentChance): void
    {
        if (mt_rand(1, 100) <= $absentChance) {
            return;
        }

        $roll = mt_rand(1, 100);
        $inTime = match (true) {
            $roll <= 20 => $this->randomTime($date, '08:00', 5, 20),    // Slightly late 5–20 min
            $roll <= 40 => $this->randomTime($date, '08:00', 21, 45),   // Moderately late 21–45 min
            $roll <= 55 => $this->randomTime($date, '08:00', 60, 120),  // Heavily late 1–2 h
            default     => $this->randomTime($date, '08:00', -15, 3),   // On time
        };

        $this->createPunch($emp, $date, PunchType::IN, $inTime);
        $this->createPunch($emp, $date, PunchType::LUNCH_OUT, $this->randomTime($date, '12:00', -5, 10));
        $this->createPunch($emp, $date, PunchType::LUNCH_IN, $this->randomTime($date, '13:00', -8, 12));

        // 20% also leave early (compounding deductions)
        $outTime = mt_rand(1, 100) <= 20
            ? $this->randomTime($date, '17:00', -30, -5)
            : $this->randomTime($date, '17:00', -5, 5);

        $this->createPunch($emp, $date, PunchType::OUT, $outTime);
    }

    private function erraticPunches(Employee $emp, string $date): void
    {
        $roll = mt_rand(1, 100);

        if ($roll <= 30) {
            // Absent
            return;
        }

        if ($roll <= 45) {
            // Half-day morning only (no lunch punch)
            $this->createPunch($emp, $date, PunchType::IN, $this->randomTime($date, '08:00', -10, 15));
            $this->createPunch($emp, $date, PunchType::OUT, $this->randomTime($date, '12:00', -10, 10));

            return;
        }

        if ($roll <= 60) {
            // Late + early out (both late and undertime deductions)
            $lateMin = mt_rand(15, 60);
            $inTime = Carbon::parse("{$date} 08:00")->addMinutes($lateMin)->format('H:i:s');
            $earlyOut = mt_rand(15, 60);
            $outTime = Carbon::parse("{$date} 17:00")->subMinutes($earlyOut)->format('H:i:s');
            $this->createPunch($emp, $date, PunchType::IN, $inTime);
            $this->createPunch($emp, $date, PunchType::LUNCH_OUT, $this->randomTime($date, '12:00', -5, 10));
            $this->createPunch($emp, $date, PunchType::LUNCH_IN, $this->randomTime($date, '13:00', -8, 10));
            $this->createPunch($emp, $date, PunchType::OUT, $outTime);

            return;
        }

        if ($roll <= 75) {
            // No lunch punches: service auto-deducts 60 min when shift ≥5 h spans lunch window
            $this->createPunch($emp, $date, PunchType::IN, $this->randomTime($date, '08:00', -10, 5));
            $this->createPunch($emp, $date, PunchType::OUT, $this->randomTime($date, '17:00', -5, 20));

            return;
        }

        $this->mostlyOnTimePunches($emp, $date, 0);
    }

    private function highAbsentPunches(Employee $emp, string $date): void
    {
        if (mt_rand(1, 100) <= 45) {
            return; // 45% absent — very high
        }
        $this->mostlyOnTimePunches($emp, $date, 0);
    }

    // ─── Prerequisite records (created before attendance computation) ─────────

    private function createLeaveRequests(): void
    {
        $count = 0;

        // Full-day leaves: attendance service treats these as paid/unpaid day off
        foreach ($this->empFullDayLeaves as $empId => $dates) {
            foreach ($dates as $date => $plan) {
                LeaveRequest::firstOrCreate(
                    ['employee_id' => $empId, 'date' => $date],
                    [
                        'leave_type' => $plan['type'],
                        'duration' => 'full_day',
                        'is_paid' => $plan['isPaid'],
                        'reason' => $plan['reason'],
                        'status' => 'approved',
                        'approved_by' => $this->approverId,
                        'approved_at' => now(),
                    ]
                );
                $count++;
            }
        }

        // Half-day leaves: metadata only — attendance is computed from actual punches
        foreach ($this->empHalfDayDates as $empId => $dates) {
            foreach ($dates as $date => $period) {
                LeaveRequest::firstOrCreate(
                    ['employee_id' => $empId, 'date' => $date],
                    [
                        'leave_type' => 'vacation',
                        'duration' => "half_{$period}",
                        'is_paid' => true,
                        'reason' => 'Half-day off',
                        'status' => 'approved',
                        'approved_by' => $this->approverId,
                        'approved_at' => now(),
                    ]
                );
                $count++;
            }
        }

        $this->components->info("   {$count} leave requests");
    }

    private function createFines(): void
    {
        $count = 0;
        foreach ($this->empFines as $empId => $fines) {
            foreach ($fines as $fine) {
                Fine::create([
                    'employee_id' => $empId,
                    'date' => $fine['date'],
                    'fine_type' => $fine['type'],
                    'amount' => $fine['amount'],
                    'note' => $fine['note'],
                    'marked_by' => $this->approverId,
                ]);
                $count++;
            }
        }
        $this->components->info("   {$count} fines");
    }

    private function assignBenefits(): void
    {
        [$sss, $philhealth, $pagibig, $rice, $laundry] = [
            Benefit::where('name', 'SSS')->first(),
            Benefit::where('name', 'PhilHealth')->first(),
            Benefit::where('name', 'Pag-IBIG')->first(),
            Benefit::where('name', 'Rice Subsidy')->first(),
            Benefit::where('name', 'Laundry Allowance')->first(),
        ];

        foreach ($this->employees as $emp) {
            if ($sss && $emp->sss_number) {
                $emp->benefits()->syncWithoutDetaching([$sss->id => ['effective_date' => '2026-01-01', 'is_active' => true]]);
            }
            if ($philhealth && $emp->philhealth_number) {
                $emp->benefits()->syncWithoutDetaching([$philhealth->id => ['effective_date' => '2026-01-01', 'is_active' => true]]);
            }
            if ($pagibig && $emp->pagibig_number) {
                $emp->benefits()->syncWithoutDetaching([$pagibig->id => ['effective_date' => '2026-01-01', 'is_active' => true]]);
            }
            // ~40% get rice subsidy, occasionally with custom amount
            if ($rice && mt_rand(1, 100) <= 40) {
                $emp->benefits()->syncWithoutDetaching([$rice->id => [
                    'effective_date' => '2026-01-01',
                    'custom_monthly_amount' => mt_rand(0, 1) ? null : 2000 + (mt_rand(0, 5) * 100),
                    'is_active' => true,
                ]]);
            }
            // ~25% get laundry allowance
            if ($laundry && mt_rand(1, 100) <= 25) {
                $emp->benefits()->syncWithoutDetaching([$laundry->id => ['effective_date' => '2026-01-01', 'is_active' => true]]);
            }
        }
    }

    // ─── Attendance & payroll ─────────────────────────────────────────────────

    private function computeAttendanceSheets(): void
    {
        $count = 0;
        foreach ($this->employees as $emp) {
            foreach ($this->dates as $date) {
                $this->attendance->processDailyAttendance($emp, $date);
                $count++;
            }
        }
        $this->components->info("   {$count} sheets processed");
    }

    private function createCashAdvances(): void
    {
        $amounts = [500, 750, 1000, 1500, 2000, 2500];
        $count = 0;
        foreach ($this->employees as $emp) {
            if (mt_rand(1, 100) <= 15) {
                $amount = $amounts[array_rand($amounts)];
                CashAdvance::create([
                    'employee_id' => $emp->id,
                    'amount' => $amount,
                    'remaining_balance' => $amount,
                    'reason' => $this->randomCAReason(),
                    'status' => 'approved',
                    'approved_by' => $this->approverId,
                    'approved_at' => now(),
                ]);
                $count++;
            }
        }
        $this->components->info("   {$count} cash advances");
    }

    private function generatePayrollPeriods(): void
    {
        $week1 = array_filter($this->dates, fn ($d) => Carbon::parse($d)->lte(Carbon::parse('2026-06-13')));
        $week2 = array_filter($this->dates, fn ($d) => Carbon::parse($d)->gte(Carbon::parse('2026-06-15')));

        foreach ([
            ['start' => min($week1), 'end' => max($week1), 'label' => 'Week 1 (Jun 8–13)'],
            ['start' => min($week2), 'end' => max($week2), 'label' => 'Week 2 (Jun 15–19)'],
        ] as $p) {
            foreach ($this->branches as $branch) {
                $exists = PayrollPeriod::where('branch_id', $branch->id)
                    ->where('period_start', $p['start'])
                    ->where('period_end', $p['end'])
                    ->exists();
                if (! $exists) {
                    $this->payrollPeriod->generate($branch, $p['start'], $p['end']);
                }
            }
            $this->components->info("   Generated {$p['label']}");
        }
    }

    // ─── Helpers ─────────────────────────────────────────────────────────────

    private function createPunch(Employee $emp, string $date, PunchType $type, string $time): void
    {
        TimeLog::create([
            'employee_id' => $emp->id,
            'type' => $type,
            'source' => PunchSource::SELF_SERVICE,
            'timestamp' => Carbon::parse("{$date} {$time}"),
        ]);
    }

    private function randomTime(string $date, string $base, int $minOffset, int $maxOffset): string
    {
        return Carbon::parse("{$date} {$base}")->addMinutes(mt_rand($minOffset, $maxOffset))->format('H:i:s');
    }

    private function randomCAReason(): string
    {
        $reasons = ['Medical expenses', 'School tuition', 'Home repair', 'Emergency travel', 'Family obligation', 'Utility bills'];

        return $reasons[array_rand($reasons)];
    }
}
