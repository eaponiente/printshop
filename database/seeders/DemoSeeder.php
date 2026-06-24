<?php

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\Customer;
use App\Models\Payroll\CashAdvance;
use App\Models\Payroll\Employee;
use App\Models\Payroll\EmployeeSchedule;
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

class DemoSeeder extends Seeder
{
    private AttendanceService $attendanceService;

    private Branch $branch;

    private float $dailyRate = 510;

    private array $workingDates;

    public function run(): void
    {
        $this->attendanceService = app(AttendanceService::class);
        $this->branch = Branch::firstOrCreate(['name' => 'Babak']);

        if ($this->branch->wasRecentlyCreated) {
            Branch::firstOrCreate(['name' => 'Tibungco']);
            Branch::firstOrCreate(['name' => 'Malita']);
            Branch::firstOrCreate(['name' => 'Peñaplata']);
        }

        // Ensure admin user exists
        User::firstOrCreate(
            ['username' => 'babak_admin'],
            [
                'first_name' => 'Admin',
                'last_name' => 'Babak',
                'password' => bcrypt('password'),
                'role' => 'admin',
                'branch_id' => $this->branch->id,
            ]
        );

        // Ensure customers exist (seed senators if empty)
        if (Customer::count() === 0) {
            app(CustomerSeeder::class)->run();
        }

        $this->workingDates = [
            '2026-06-15', // Monday
            '2026-06-16', // Tuesday
            '2026-06-17', // Wednesday
            '2026-06-18', // Thursday
            '2026-06-19', // Friday
            '2026-06-22', // Monday (after weekend)
        ];

        $this->seedTags();
        $this->seedSublimations();
        $this->seedAttendance();
    }

    // ─── Tags ────────────────────────────────────────────────────────

    private function seedTags(): void
    {
        // Status tags
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

        // Sewing tags (items, with price_per_piece)
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

    // ─── Sublimations ─────────────────────────────────────────────────

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

            $tagIds = $allTags->random(rand(1, 3))->pluck('id')->toArray();
            $sublimation->tags()->attach($tagIds);
        }
    }

    // ─── Attendance ───────────────────────────────────────────────────

    private function seedAttendance(): void
    {
        $this->seedPerfectEmployee();
        $this->seedLeaveEmployee();
        $this->seedAbsentEmployee();
        $this->seedLateEmployee();
        $this->seedCashAdvanceEmployee();
    }

    // ─── Employee helpers ─────────────────────────────────────────────

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

    private function createSchedule(Employee $emp, array $restDays = [0, 6]): void
    {
        EmployeeSchedule::firstOrCreate(
            ['employee_id' => $emp->id, 'effective_from' => '2026-06-15'],
            [
                'start_time' => '08:00',
                'end_time' => '17:00',
                'rest_days' => $restDays,
                'effective_to' => null,
                'is_active' => true,
            ]
        );
    }

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

    private function perfectPunches(Employee $emp, string $date): void
    {
        $this->punch($emp, $date, PunchType::IN, '08:00:00');
        $this->punch($emp, $date, PunchType::LUNCH_OUT, '12:00:00');
        $this->punch($emp, $date, PunchType::LUNCH_IN, '13:00:00');
        $this->punch($emp, $date, PunchType::OUT, '17:00:00');
    }

    private function computeAttendance(Employee $emp): void
    {
        foreach ($this->workingDates as $date) {
            $this->attendanceService->processDailyAttendance($emp, $date);
        }
        // Also process rest days (June 20=Sat, June 21=Sun)
        $this->attendanceService->processDailyAttendance($emp, '2026-06-20');
        $this->attendanceService->processDailyAttendance($emp, '2026-06-21');
    }

    // ─── Employee 1: Perfect Attendance ───────────────────────────────

    private function seedPerfectEmployee(): void
    {
        $emp = $this->createEmployee('Maria', 'Perfecto');
        $this->createSchedule($emp);

        foreach ($this->workingDates as $date) {
            $this->perfectPunches($emp, $date);
        }

        $this->computeAttendance($emp);
    }

    // ─── Employee 2: With Paid Leave (Thu-Fri) ────────────────────────

    private function seedLeaveEmployee(): void
    {
        $emp = $this->createEmployee('Juan', 'Bakasyon');
        $this->createSchedule($emp);

        // Present Mon-Wed
        $this->perfectPunches($emp, '2026-06-15');
        $this->perfectPunches($emp, '2026-06-16');
        $this->perfectPunches($emp, '2026-06-17');

        // Leave Thu-Fri (paid vacation leave)
        foreach (['2026-06-18', '2026-06-19'] as $date) {
            LeaveRequest::firstOrCreate(
                ['employee_id' => $emp->id, 'date' => $date],
                [
                    'leave_type' => 'vacation',
                    'duration' => 'full_day',
                    'is_paid' => true,
                    'reason' => 'Family matter',
                    'status' => 'approved',
                    'approved_by' => User::first()->id,
                    'approved_at' => now(),
                ]
            );
        }

        // Present on Monday after leave
        $this->perfectPunches($emp, '2026-06-22');

        $this->computeAttendance($emp);
    }

    // ─── Employee 3: Absences (unexcused Tue-Wed) ─────────────────────

    private function seedAbsentEmployee(): void
    {
        $emp = $this->createEmployee('Pedro', 'Absentado');
        $this->createSchedule($emp);

        // Present Mon
        $this->perfectPunches($emp, '2026-06-15');

        // No punches on Tue-Wed (unexcused absence)

        // Present Thu-Fri, Mon
        $this->perfectPunches($emp, '2026-06-18');
        $this->perfectPunches($emp, '2026-06-19');
        $this->perfectPunches($emp, '2026-06-22');

        $this->computeAttendance($emp);
    }

    // ─── Employee 4: Chronically Late ─────────────────────────────────

    private function seedLateEmployee(): void
    {
        $emp = $this->createEmployee('Anna', 'Latecomer');
        $this->createSchedule($emp);

        foreach ($this->workingDates as $date) {
            $this->punch($emp, $date, PunchType::IN, '09:30:00');
            $this->punch($emp, $date, PunchType::LUNCH_OUT, '12:00:00');
            $this->punch($emp, $date, PunchType::LUNCH_IN, '13:00:00');
            $this->punch($emp, $date, PunchType::OUT, '17:00:00');
        }

        $this->computeAttendance($emp);
    }

    // ─── Employee 5: With Cash Advances ───────────────────────────────

    private function seedCashAdvanceEmployee(): void
    {
        $emp = $this->createEmployee('Carlos', 'Utangan');
        $this->createSchedule($emp);

        // Perfect attendance all working days
        foreach ($this->workingDates as $date) {
            $this->perfectPunches($emp, $date);
        }

        $this->computeAttendance($emp);

        // Cash Advance 1 - approved (to be deducted)
        CashAdvance::create([
            'employee_id' => $emp->id,
            'amount' => 3000.00,
            'remaining_balance' => 3000.00,
            'reason' => 'Medical emergency',
            'status' => 'approved',
            'approved_by' => User::first()->id,
            'approved_at' => Carbon::parse('2026-06-15 09:00:00'),
        ]);

        // Cash Advance 2 - pending (not yet deducted)
        CashAdvance::create([
            'employee_id' => $emp->id,
            'amount' => 1500.00,
            'remaining_balance' => 1500.00,
            'reason' => 'Tuition fee',
            'status' => 'pending',
            'approved_by' => null,
            'approved_at' => null,
        ]);

        // Cash Advance 3 - partially paid (with remaining balance)
        $ca = CashAdvance::create([
            'employee_id' => $emp->id,
            'amount' => 5000.00,
            'remaining_balance' => 2000.00,
            'reason' => 'Salary advance',
            'status' => 'approved',
            'approved_by' => User::first()->id,
            'approved_at' => Carbon::parse('2026-06-10 10:00:00'),
        ]);
    }
}
