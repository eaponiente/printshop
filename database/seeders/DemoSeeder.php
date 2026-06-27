<?php

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\Customer;
use App\Models\Payroll\CashAdvance;
use App\Models\Payroll\Employee;
use App\Models\Payroll\Fine;
use App\Models\Payroll\LeaveRequest;
use App\Models\Payroll\Salary;
use App\Models\Payroll\TimeLog;
use App\Models\Sublimation;
use App\Models\Tag;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Payroll\Attendance\Enums\PunchSource;
use Payroll\Attendance\Enums\PunchType;
use Payroll\Attendance\Services\AttendanceService;
use Payroll\Employee\Services\EmployeeService;

class DemoSeeder extends Seeder
{
    private AttendanceService $attendanceService;

    private EmployeeService $employeeService;

    private Branch $branch;

    /** @var string[] */
    private array $allJuneDates;

    private User $approver;

    /** Govt IDs assigned in order of employees synced. */
    private array $govtIds = [
        ['sss' => '33-1234567-8', 'phic' => '01-234567890-1', 'hdmf' => '1234-5678-9012'],
        ['sss' => '33-2345678-9', 'phic' => '01-345678901-2', 'hdmf' => '2345-6789-0123'],
        ['sss' => '33-3456789-0', 'phic' => '01-456789012-3', 'hdmf' => '3456-7890-1234'],
        ['sss' => '33-4567890-1', 'phic' => '01-567890123-4', 'hdmf' => '4567-8901-2345'],
    ];

    /** Attendance scenario methods, cycled if fewer users than scenarios. */
    private array $scenarios = [
        'scenarioNearPerfect',
        'scenarioFrequentLates',
        'scenarioAbsencesAndFine',
        'scenarioGoodWithCAs',
    ];

    public function run(): void
    {
        $this->attendanceService = app(AttendanceService::class);
        $this->employeeService = app(EmployeeService::class);
        $this->branch = Branch::firstOrCreate(['name' => 'Babak']);

        if ($this->branch->wasRecentlyCreated) {
            Branch::firstOrCreate(['name' => 'Tibungco']);
            Branch::firstOrCreate(['name' => 'Malita']);
            Branch::firstOrCreate(['name' => 'Peñaplata']);
        }

        $this->allJuneDates = collect(range(1, 26))
            ->map(fn ($d) => sprintf('2026-06-%02d', $d))
            ->all();

        $this->approver = User::where('role', 'superadmin')->first()
            ?? User::where('role', 'admin')->where('branch_id', $this->branch->id)->first()
            ?? User::first();

        if (Customer::count() === 0) {
            app(CustomerSeeder::class)->run();
        }

        $this->seedTags();
        $this->seedSublimations();
        $this->seedAttendance();
    }

    // ─── Tags ─────────────────────────────────────────────────────────────────

    private function seedTags(): void
    {
        $statusTags = [
            ['name' => 'Rush Order', 'color' => '#dc2626', 'price_per_piece' => 0],
            ['name' => 'Awaiting Artwork', 'color' => '#f97316', 'price_per_piece' => 0],
            ['name' => 'Proof Sent', 'color' => '#eab308', 'price_per_piece' => 0],
            ['name' => 'Ready to Press', 'color' => '#16a34a', 'price_per_piece' => 0],
            ['name' => 'Printed', 'color' => '#3b82f6', 'price_per_piece' => 0],
            ['name' => 'Quality Control Failed', 'color' => '#be123c', 'price_per_piece' => 0],
            ['name' => 'Reprint Needed', 'color' => '#9333ea', 'price_per_piece' => 0],
            ['name' => 'On Hold', 'color' => '#334155', 'price_per_piece' => 0],
        ];

        foreach ($statusTags as $tag) {
            Tag::firstOrCreate(['name' => $tag['name']], $tag);
        }

        $sewingTags = [
            ['name' => 'Jersey', 'color' => '#14b8a6', 'price_per_piece' => 35.00],
            ['name' => 'T-Shirt', 'color' => '#06b6d4', 'price_per_piece' => 30.00],
            ['name' => 'Lanyard', 'color' => '#84cc16', 'price_per_piece' => 10.00],
            ['name' => 'Hoodie', 'color' => '#f59e0b', 'price_per_piece' => 75.00],
            ['name' => 'Shorts', 'color' => '#8b5cf6', 'price_per_piece' => 25.00],
            ['name' => 'Cap', 'color' => '#ec4899', 'price_per_piece' => 20.00],
            ['name' => 'Jacket', 'color' => '#10b981', 'price_per_piece' => 120.00],
            ['name' => 'Polo Shirt', 'color' => '#0ea5e9', 'price_per_piece' => 45.00],
        ];

        foreach ($sewingTags as $tag) {
            Tag::firstOrCreate(['name' => $tag['name']], $tag);
        }
    }

    // ─── Sublimations ─────────────────────────────────────────────────────────

    private function seedSublimations(): void
    {
        $customers = Customer::all();
        $admin = User::where('role', 'admin')->where('branch_id', $this->branch->id)->first()
            ?? User::first();

        $sublimationData = [
            ['amount_total' => 35000, 'quantity' => 100, 'description' => 'Team Jersey - Basketball Varsity', 'transaction_type' => 'purchase_order', 'due_at' => '2026-07-15'],
            ['amount_total' => 12500, 'quantity' => 50, 'description' => 'Corporate Lanyards - Event Giveaway', 'transaction_type' => 'retail', 'due_at' => '2026-07-01'],
            ['amount_total' => 48000, 'quantity' => 80, 'description' => 'Custom Hoodies - Company Merch', 'transaction_type' => 'purchase_order', 'due_at' => '2026-07-20'],
            ['amount_total' => 15000, 'quantity' => 200, 'description' => 'Sublimation Mugs - Anniversary', 'transaction_type' => 'retail', 'due_at' => '2026-06-30'],
            ['amount_total' => 22000, 'quantity' => 60, 'description' => 'Full Sublimation Jersey - Volleyball', 'transaction_type' => 'purchase_order', 'due_at' => '2026-07-10'],
            ['amount_total' => 8500, 'quantity' => 100, 'description' => 'ID Lace with Print - Senate Staff', 'transaction_type' => 'purchase_order', 'due_at' => '2026-07-05'],
            ['amount_total' => 60000, 'quantity' => 150, 'description' => 'Polo Shirts w/ Embroidery - Barangay', 'transaction_type' => 'purchase_order', 'due_at' => '2026-08-01'],
            ['amount_total' => 9500, 'quantity' => 30, 'description' => 'Caps with Logo Print - Political', 'transaction_type' => 'retail', 'due_at' => '2026-06-28'],
            ['amount_total' => 18000, 'quantity' => 40, 'description' => 'Jacket Sublimation - Team Building', 'transaction_type' => 'purchase_order', 'due_at' => '2026-07-25'],
            ['amount_total' => 32000, 'quantity' => 250, 'description' => 'Short Pants - Intramurals', 'transaction_type' => 'retail', 'due_at' => '2026-07-08'],
        ];

        $allTags = Tag::all();

        foreach ($sublimationData as $data) {
            $sublimation = Sublimation::query()->create([
                'branch_id' => $this->branch->id,
                'user_id' => $admin->id,
                'status' => 'for_approval',
                'production_authorized' => false,
                'amount_total' => $data['amount_total'],
                'quantity' => $data['quantity'],
                'description' => $data['description'],
                'transaction_type' => $data['transaction_type'],
                'notes' => null,
                'due_at' => $data['due_at'],
                'customer_id' => $customers->random()->id,
            ]);

            $sublimation->tags()->attach($allTags->random(rand(1, 3))->pluck('id')->toArray());
        }
    }

    // ─── Attendance ────────────────────────────────────────────────────────────

    private function seedAttendance(): void
    {
        $users = User::where('branch_id', $this->branch->id)
            ->whereIn('role', ['admin', 'staff'])
            ->get();

        foreach ($users->values() as $index => $user) {
            $emp = $user->employee_id
                ? Employee::find($user->employee_id)
                : $this->employeeService->syncUser($user);

            // Override defaults set by syncUser with real demo values
            $ids = $this->govtIds[$index % count($this->govtIds)];
            $emp->update([
                'current_daily_rate' => 510,
                'hire_date' => '2026-01-05',
                'sss_number' => $ids['sss'],
                'philhealth_number' => $ids['phic'],
                'pagibig_number' => $ids['hdmf'],
            ]);

            // Back-date the schedule so it covers June 1
            $emp->schedules()->update(['effective_from' => '2026-01-01']);

            if (! Salary::where('employee_id', $emp->id)->exists()) {
                Salary::createForEmployee($emp, 510, '2026-01-05', 'Initial salary');
            }

            $scenario = $this->scenarios[$index % count($this->scenarios)];
            $this->$scenario($emp);
            $this->processAttendance($emp);
        }
    }

    // ─── Punch helpers ─────────────────────────────────────────────────────────
    // Schedule from syncUser: 08:00–17:30 with 30 min unpaid tail = 8 paid hours.
    // Regular OUT at 17:30; overtime starts after that.

    private function punch(Employee $emp, string $date, PunchType $type, string $time): void
    {
        TimeLog::firstOrCreate(
            [
                'employee_id' => $emp->id,
                'type' => $type->value,
                'timestamp' => "{$date} {$time}",
            ],
            ['source' => PunchSource::SELF_SERVICE]
        );
    }

    private function perfectDay(Employee $emp, string $date): void
    {
        $this->punch($emp, $date, PunchType::IN, '08:00:00');
        $this->punch($emp, $date, PunchType::LUNCH_OUT, '12:00:00');
        $this->punch($emp, $date, PunchType::LUNCH_IN, '13:00:00');
        $this->punch($emp, $date, PunchType::OUT, '17:30:00');
    }

    private function lateDay(Employee $emp, string $date, string $inTime): void
    {
        $this->punch($emp, $date, PunchType::IN, $inTime);
        $this->punch($emp, $date, PunchType::LUNCH_OUT, '12:00:00');
        $this->punch($emp, $date, PunchType::LUNCH_IN, '13:00:00');
        $this->punch($emp, $date, PunchType::OUT, '17:30:00');
    }

    private function undertimeDay(Employee $emp, string $date, string $outTime): void
    {
        $this->punch($emp, $date, PunchType::IN, '08:00:00');
        $this->punch($emp, $date, PunchType::LUNCH_OUT, '12:00:00');
        $this->punch($emp, $date, PunchType::LUNCH_IN, '13:00:00');
        $this->punch($emp, $date, PunchType::OUT, $outTime);
    }

    private function overtimeDay(Employee $emp, string $date, string $otOutTime): void
    {
        $this->perfectDay($emp, $date);
        $this->punch($emp, $date, PunchType::OVERTIME_IN, '17:35:00');
        $this->punch($emp, $date, PunchType::OVERTIME_OUT, $otOutTime);
    }

    /** Has IN + lunch but no OUT punch → "Punch-out missing" */
    private function noPunchOutDay(Employee $emp, string $date): void
    {
        $this->punch($emp, $date, PunchType::IN, '08:00:00');
        $this->punch($emp, $date, PunchType::LUNCH_OUT, '12:00:00');
        $this->punch($emp, $date, PunchType::LUNCH_IN, '13:00:00');
    }

    /** Has lunch + OUT but no IN punch → "No punch-in recorded" */
    private function noPunchInDay(Employee $emp, string $date): void
    {
        $this->punch($emp, $date, PunchType::LUNCH_OUT, '12:00:00');
        $this->punch($emp, $date, PunchType::LUNCH_IN, '13:00:00');
        $this->punch($emp, $date, PunchType::OUT, '17:30:00');
    }

    /** Has LUNCH_IN but no LUNCH_OUT → "Lunch break punch missing" */
    private function noLunchOutDay(Employee $emp, string $date): void
    {
        $this->punch($emp, $date, PunchType::IN, '08:00:00');
        $this->punch($emp, $date, PunchType::LUNCH_IN, '13:00:00');
        $this->punch($emp, $date, PunchType::OUT, '17:30:00');
    }

    /** Has LUNCH_OUT but no LUNCH_IN → "Lunch return punch missing" */
    private function noLunchInDay(Employee $emp, string $date): void
    {
        $this->punch($emp, $date, PunchType::IN, '08:00:00');
        $this->punch($emp, $date, PunchType::LUNCH_OUT, '12:00:00');
        $this->punch($emp, $date, PunchType::OUT, '17:30:00');
    }

    private function processAttendance(Employee $emp): void
    {
        foreach ($this->allJuneDates as $date) {
            $this->attendanceService->processDailyAttendance($emp, $date);
        }
    }

    // ─── Scenario A: Near-perfect ──────────────────────────────────────────────
    // 3 lates, 1 paid sick leave (Jun 18), 1 overtime (Jun 22), 1 CA
    // Works on holiday Jun 12 (Independence Day) → earns holiday pay
    // Jun 19: missing LUNCH_OUT punch → flagged "Lunch break punch missing"

    private function scenarioNearPerfect(Employee $emp): void
    {
        $leave = ['2026-06-18'];
        $sundays = ['2026-06-07', '2026-06-14', '2026-06-21'];

        foreach ($this->allJuneDates as $date) {
            if (in_array($date, $sundays) || in_array($date, $leave)) {
                continue;
            }
            match ($date) {
                '2026-06-03' => $this->lateDay($emp, $date, '08:35:00'),
                '2026-06-10' => $this->lateDay($emp, $date, '08:28:00'),
                '2026-06-19' => $this->noLunchOutDay($emp, $date),
                '2026-06-22' => $this->overtimeDay($emp, $date, '19:00:00'),
                '2026-06-25' => $this->lateDay($emp, $date, '08:22:00'),
                default => $this->perfectDay($emp, $date),
            };
        }

        LeaveRequest::firstOrCreate(
            ['employee_id' => $emp->id, 'date' => '2026-06-18'],
            [
                'leave_type' => 'sick',
                'duration' => 'full_day',
                'is_paid' => true,
                'reason' => 'Fever and flu',
                'status' => 'approved',
                'approved_by' => $this->approver->id,
                'approved_at' => Carbon::parse('2026-06-18 07:00:00'),
            ]
        );

        if (! CashAdvance::where('employee_id', $emp->id)->exists()) {
            CashAdvance::create([
                'employee_id' => $emp->id,
                'amount' => 2000.00,
                'remaining_balance' => 2000.00,
                'reason' => 'Medical expenses',
                'status' => 'approved',
                'approved_by' => $this->approver->id,
                'approved_at' => Carbon::parse('2026-06-05 09:00:00'),
            ]);
        }
    }

    // ─── Scenario B: Frequent lates ────────────────────────────────────────────
    // 4 lates, 1 absence WITH fine (Jun 8 — unexcused, no show), 2 overtimes, CA×2
    // Works on holiday Jun 12 (Independence Day) → earns holiday pay
    // Jun 20: missing OUT punch → flagged "Punch-out missing"

    private function scenarioFrequentLates(Employee $emp): void
    {
        $absent = ['2026-06-08'];
        $sundays = ['2026-06-07', '2026-06-14', '2026-06-21'];

        foreach ($this->allJuneDates as $date) {
            if (in_array($date, $sundays) || in_array($date, $absent)) {
                continue;
            }
            match ($date) {
                '2026-06-01' => $this->lateDay($emp, $date, '08:18:00'),
                '2026-06-02' => $this->lateDay($emp, $date, '08:40:00'),
                '2026-06-16' => $this->lateDay($emp, $date, '08:52:00'),
                '2026-06-17' => $this->overtimeDay($emp, $date, '20:00:00'),
                '2026-06-20' => $this->noPunchOutDay($emp, $date),
                '2026-06-23' => $this->overtimeDay($emp, $date, '18:30:00'),
                '2026-06-26' => $this->lateDay($emp, $date, '08:15:00'),
                default => $this->perfectDay($emp, $date),
            };
        }

        if (! Fine::where('employee_id', $emp->id)->exists()) {
            Fine::create([
                'employee_id' => $emp->id,
                'date' => '2026-06-08',
                'fine_type' => 'unexcused_absence',
                'amount' => 200.00,
                'note' => 'No notice given for absence',
                'marked_by' => $this->approver->id,
            ]);
        }

        if (! CashAdvance::where('employee_id', $emp->id)->exists()) {
            CashAdvance::create([
                'employee_id' => $emp->id,
                'amount' => 3000.00,
                'remaining_balance' => 3000.00,
                'reason' => 'Medical emergency',
                'status' => 'approved',
                'approved_by' => $this->approver->id,
                'approved_at' => Carbon::parse('2026-06-10 09:00:00'),
            ]);

            CashAdvance::create([
                'employee_id' => $emp->id,
                'amount' => 1500.00,
                'remaining_balance' => 1500.00,
                'reason' => 'Tuition fee advance',
                'status' => 'pending',
                'approved_by' => null,
                'approved_at' => null,
            ]);
        }
    }

    // ─── Scenario C: Absences and fine ─────────────────────────────────────────
    // 3 absences with NO fine (Jun 4, 5, 13 — just didn't show, no penalty)
    // Absent on holiday Jun 12 — no pay, no fine (holiday)
    // Jun 18: missing IN punch → flagged "No punch-in recorded"
    // Misconduct fine on Jun 11 (independent of attendance)

    private function scenarioAbsencesAndFine(Employee $emp): void
    {
        $absent = ['2026-06-04', '2026-06-05', '2026-06-12', '2026-06-13'];
        $sundays = ['2026-06-07', '2026-06-14', '2026-06-21'];

        foreach ($this->allJuneDates as $date) {
            if (in_array($date, $sundays) || in_array($date, $absent)) {
                continue;
            }
            match ($date) {
                '2026-06-09' => $this->lateDay($emp, $date, '09:02:00'),
                '2026-06-11' => $this->lateDay($emp, $date, '08:45:00'),
                '2026-06-18' => $this->noPunchInDay($emp, $date),
                '2026-06-19' => $this->undertimeDay($emp, $date, '15:30:00'),
                '2026-06-20' => $this->lateDay($emp, $date, '08:58:00'),
                '2026-06-24' => $this->overtimeDay($emp, $date, '19:30:00'),
                default => $this->perfectDay($emp, $date),
            };
        }

        if (! Fine::where('employee_id', $emp->id)->exists()) {
            Fine::create([
                'employee_id' => $emp->id,
                'date' => '2026-06-11',
                'fine_type' => 'misconduct',
                'amount' => 300.00,
                'note' => 'Inappropriate behavior reported by branch admin',
                'marked_by' => $this->approver->id,
            ]);
        }

        if (! CashAdvance::where('employee_id', $emp->id)->exists()) {
            CashAdvance::create([
                'employee_id' => $emp->id,
                'amount' => 5000.00,
                'remaining_balance' => 2500.00,
                'reason' => 'Emergency house repair',
                'status' => 'approved',
                'approved_by' => $this->approver->id,
                'approved_at' => Carbon::parse('2026-05-20 10:00:00'),
            ]);
        }
    }

    // ─── Scenario D: Good attendance with CAs ──────────────────────────────────
    // 2 small lates, 1 absence with NO fine (Jun 8 — just missed, no penalty)
    // Absent on holiday Jun 12 — no pay, no fine (holiday)
    // 2-day paid vacation leave (Jun 15–16)
    // Jun 18: LUNCH_OUT with no LUNCH_IN → flagged "Lunch return punch missing"
    // 2 overtimes, CA×3

    private function scenarioGoodWithCAs(Employee $emp): void
    {
        $absent = ['2026-06-08', '2026-06-12'];
        $leave = ['2026-06-15', '2026-06-16'];
        $sundays = ['2026-06-07', '2026-06-14', '2026-06-21'];

        foreach ($this->allJuneDates as $date) {
            if (in_array($date, $sundays) || in_array($date, $absent) || in_array($date, $leave)) {
                continue;
            }
            match ($date) {
                '2026-06-02' => $this->lateDay($emp, $date, '08:20:00'),
                '2026-06-06' => $this->lateDay($emp, $date, '08:30:00'),
                '2026-06-18' => $this->noLunchInDay($emp, $date),
                '2026-06-22' => $this->overtimeDay($emp, $date, '19:00:00'),
                '2026-06-25' => $this->overtimeDay($emp, $date, '20:00:00'),
                default => $this->perfectDay($emp, $date),
            };
        }

        foreach ($leave as $leaveDate) {
            LeaveRequest::firstOrCreate(
                ['employee_id' => $emp->id, 'date' => $leaveDate],
                [
                    'leave_type' => 'vacation',
                    'duration' => 'full_day',
                    'is_paid' => true,
                    'reason' => 'Family vacation',
                    'status' => 'approved',
                    'approved_by' => $this->approver->id,
                    'approved_at' => Carbon::parse('2026-06-10 09:00:00'),
                ]
            );
        }

        if (! CashAdvance::where('employee_id', $emp->id)->exists()) {
            CashAdvance::create([
                'employee_id' => $emp->id,
                'amount' => 4000.00,
                'remaining_balance' => 4000.00,
                'reason' => 'School supplies for children',
                'status' => 'approved',
                'approved_by' => $this->approver->id,
                'approved_at' => Carbon::parse('2026-06-01 08:30:00'),
            ]);

            CashAdvance::create([
                'employee_id' => $emp->id,
                'amount' => 2000.00,
                'remaining_balance' => 2000.00,
                'reason' => 'Transportation expenses',
                'status' => 'pending',
                'approved_by' => null,
                'approved_at' => null,
            ]);

            CashAdvance::create([
                'employee_id' => $emp->id,
                'amount' => 6000.00,
                'remaining_balance' => 3000.00,
                'reason' => 'Hospitalization',
                'status' => 'approved',
                'approved_by' => $this->approver->id,
                'approved_at' => Carbon::parse('2026-05-15 11:00:00'),
            ]);
        }
    }
}
