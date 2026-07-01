<?php

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\Payroll\Employee;
use App\Models\Payroll\EmployeeSchedule;
use App\Models\Payroll\Salary;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Payroll\Attendance\Enums\PunchType;
use Payroll\Attendance\Services\TimeLogService;

/**
 * Seeds attendance via TimeLogService::punch() (never TimeLog::create directly)
 * so AttendanceService::processDailyAttendance runs exactly as it does in production.
 *
 * Range: Monday of last week through Sunday of next week (relative to today),
 * single day shift per employee (no split shift, no night shift). Overtime punches,
 * when present, always start after the day's OUT punch.
 */
class AttendanceDemoSeeder extends Seeder
{
    private TimeLogService $timeLogService;

    private Branch $branch;

    private Carbon $rangeStart;

    private Carbon $rangeEnd;

    public function run(): void
    {
        $this->timeLogService = app(TimeLogService::class);
        $this->branch = Branch::first() ?? Branch::firstOrCreate(['name' => 'Babak']);

        $this->rangeStart = Carbon::now()->startOfWeek(Carbon::MONDAY)->subWeek();
        $this->rangeEnd = Carbon::now()->startOfWeek(Carbon::MONDAY)->addWeeks(2)->subDay();

        $this->seedEmployee('Alex', 'Rivera', ['perfect' => 1.0]);
        $this->seedEmployee('Marco', 'Delacruz', ['perfect' => 0.5, 'overtime' => 0.5]);
        $this->seedEmployee('Sofia', 'Ramos', ['perfect' => 0.5, 'late' => 0.5]);
        $this->seedEmployee('Ella', 'Santos', ['perfect' => 0.4, 'undertime' => 0.4, 'absent' => 0.2]);
        $this->seedEmployee('Noel', 'Bautista', ['perfect' => 0.3, 'late' => 0.25, 'overtime' => 0.25, 'undertime' => 0.1, 'absent' => 0.1]);
    }

    /**
     * @param  array<string, float>  $weights  scenario name => probability, must sum to 1.0
     */
    private function seedEmployee(string $first, string $last, array $weights): void
    {
        $employee = $this->createEmployee($first, $last);
        $this->createSchedule($employee);

        $scenarios = array_keys($weights);
        $probabilities = array_values($weights);

        $date = $this->rangeStart->copy();

        while ($date->lte($this->rangeEnd)) {
            if ($date->dayOfWeek !== Carbon::SUNDAY) {
                $scenario = $this->weightedPick($scenarios, $probabilities);
                $this->punchDay($employee, $date->copy(), $scenario);
            }

            $date->addDay();
        }
    }

    private function createEmployee(string $first, string $last): Employee
    {
        $employee = Employee::firstOrCreate(
            ['first_name' => $first, 'last_name' => $last],
            [
                'branch_id' => $this->branch->id,
                'hire_date' => $this->rangeStart->copy()->subMonths(3)->toDateString(),
                'position' => 'regular',
                'status' => 'active',
                'current_daily_rate' => fake()->randomElement([450, 480, 500, 520, 560]),
                'sss_number' => '34-'.fake()->numerify('#######').'-'.fake()->numerify('#'),
                'philhealth_number' => fake()->numerify('##-#########-#'),
                'pagibig_number' => fake()->numerify('####-####-####'),
            ]
        );

        if (! Salary::where('employee_id', $employee->id)->exists()) {
            Salary::createForEmployee($employee, (float) $employee->current_daily_rate, $employee->hire_date->toDateString(), 'Seeded initial salary');
        }

        User::updateOrCreate(
            ['username' => strtolower($first).'.'.strtolower($last)],
            [
                'first_name' => $first,
                'last_name' => $last,
                'password' => bcrypt('password'),
                'role' => 'staff',
                'branch_id' => $this->branch->id,
                'employee_id' => $employee->id,
            ]
        );

        return $employee;
    }

    private function createSchedule(Employee $employee): void
    {
        EmployeeSchedule::firstOrCreate(
            ['employee_id' => $employee->id, 'effective_from' => $this->rangeStart->copy()->subMonths(3)->toDateString()],
            [
                'start_time' => '08:00',
                'end_time' => '17:00',
                'rest_days' => [Carbon::SUNDAY],
                'effective_to' => null,
                'is_active' => true,
            ]
        );
    }

    private function punchDay(Employee $employee, Carbon $date, string $scenario): void
    {
        $user = $employee->user;

        if ($scenario === 'absent') {
            return;
        }

        $inTime = match ($scenario) {
            'late' => $this->timeAt($date, 8, fake()->numberBetween(20, 105)), // 08:20 – 09:45
            default => $this->timeAt($date, 7, fake()->numberBetween(50, 70)), // 07:50 – 08:10
        };
        $this->punch($employee, $user, PunchType::IN, $inTime);

        $skipLunch = $scenario === 'undertime' && fake()->boolean(40);

        if (! $skipLunch) {
            $lunchOut = $this->timeAt($date, 12, fake()->numberBetween(0, 15));
            $this->punch($employee, $user, PunchType::LUNCH_OUT, $lunchOut);

            $lunchIn = $this->timeAt($date, 12, fake()->numberBetween(45, 75)); // 12:45 – 13:15
            $this->punch($employee, $user, PunchType::LUNCH_IN, $lunchIn);
        }

        $outTime = match ($scenario) {
            'undertime' => $this->timeAt($date, 15, fake()->numberBetween(0, 90)), // 15:00 – 16:30
            default => $this->timeAt($date, 17, fake()->numberBetween(0, 20)), // 17:00 – 17:20
        };
        $this->punch($employee, $user, PunchType::OUT, $outTime);

        if ($scenario === 'overtime') {
            $otIn = $outTime->copy()->addMinutes(fake()->numberBetween(15, 45));
            $this->punch($employee, $user, PunchType::OVERTIME_IN, $otIn);

            $otOut = $otIn->copy()->addMinutes(fake()->numberBetween(60, 180));
            $this->punch($employee, $user, PunchType::OVERTIME_OUT, $otOut);
        }
    }

    private function punch(Employee $employee, User $user, PunchType $type, Carbon $timestamp): void
    {
        $this->timeLogService->punch($employee, $type, $user, timestamp: $timestamp);
    }

    private function timeAt(Carbon $date, int $hour, int $extraMinutes): Carbon
    {
        return $date->copy()->setTime($hour, 0, 0)->addMinutes($extraMinutes);
    }

    /**
     * @param  array<int, string>  $scenarios
     * @param  array<int, float>  $probabilities
     */
    private function weightedPick(array $scenarios, array $probabilities): string
    {
        $roll = fake()->randomFloat(4, 0, 1);
        $cumulative = 0.0;

        foreach ($scenarios as $index => $scenario) {
            $cumulative += $probabilities[$index];

            if ($roll <= $cumulative) {
                return $scenario;
            }
        }

        return $scenarios[array_key_last($scenarios)];
    }
}
