<?php

use App\Models\Branch;
use App\Models\Payroll\AttendanceSheet;
use App\Models\Payroll\Employee;
use App\Models\Payroll\EmployeeSchedule;
use App\Models\Payroll\OvertimeRequest;
use App\Models\Payroll\TimeLog;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Payroll\Attendance\Enums\PunchSource;
use Payroll\Attendance\Enums\PunchType;
use Payroll\Attendance\Services\AttendanceService;

// Rest days (incl. Sunday) are paid as ordinary working days: hours worked at
// the normal hourly rate with no premium, and any overtime at the regular OT
// rate. Not working a rest day stays a day off (no absence).

beforeEach(function () {
    Cache::flush();

    $branch = Branch::factory()->create(['name' => 'Rest Day Branch']);

    $this->admin = User::factory()->create([
        'branch_id' => $branch->id,
        'role' => 'admin',
    ]);

    $this->employee = Employee::create([
        'first_name' => 'Rest',
        'last_name' => 'Day',
        'branch_id' => $branch->id,
        'current_daily_rate' => 600,
        'status' => 'active',
        'hire_date' => now()->subYear()->toDateString(),
        'position' => 'regular',
    ]);

    EmployeeSchedule::create([
        'employee_id' => $this->employee->id,
        'start_time' => '08:00',
        'end_time' => '17:00',
        'rest_days' => [0, 6], // Sunday + Saturday
        'effective_from' => now()->subMonth()->toDateString(),
    ]);

    $this->service = app(AttendanceService::class);
    $this->hourlyRate = 600 / 8; // 75
    $this->sunday = '2026-06-21'; // a Sunday
});

it('pays a full worked rest day as one regular daily rate — no premium', function () {
    $date = $this->sunday;

    foreach ([
        ['08:00', PunchType::IN],
        ['12:00', PunchType::LUNCH_OUT],
        ['13:00', PunchType::LUNCH_IN],
        ['17:00', PunchType::OUT],
    ] as [$time, $type]) {
        TimeLog::create([
            'employee_id' => $this->employee->id,
            'timestamp' => Carbon::parse("{$date} {$time}"),
            'type' => $type,
            'source' => PunchSource::SELF_SERVICE,
        ]);
    }

    $sheet = $this->service->processDailyAttendance($this->employee, $date);

    expect((bool) $sheet->is_rest_day)->toBeTrue();
    expect((float) $sheet->hours_worked)->toBe(8.0);
    // 8h × ₱75 = ₱600 (a plain daily rate). The old premium would have been ₱780.
    expect((float) $sheet->daily_wage)->toBe(600.0);
    expect((float) $sheet->overtime_pay)->toBe(0.0);
});

it('pays a partial worked rest day pro-rata by hours', function () {
    $date = $this->sunday;

    foreach ([
        ['08:00', PunchType::IN],
        ['12:00', PunchType::LUNCH_OUT],
        ['13:00', PunchType::LUNCH_IN],
        ['15:00', PunchType::OUT],
    ] as [$time, $type]) {
        TimeLog::create([
            'employee_id' => $this->employee->id,
            'timestamp' => Carbon::parse("{$date} {$time}"),
            'type' => $type,
            'source' => PunchSource::SELF_SERVICE,
        ]);
    }

    $sheet = $this->service->processDailyAttendance($this->employee, $date);

    expect((bool) $sheet->is_rest_day)->toBeTrue();
    expect((float) $sheet->hours_worked)->toBe(6.0);
    // 6h × ₱75 = ₱450 (pro-rata, no premium). Premium would have been ₱585.
    expect((float) $sheet->daily_wage)->toBe(450.0);
});

it('bills overtime on a rest day at the regular OT rate, not the rest-day rate', function () {
    $date = $this->sunday;

    // Full 8h worked...
    foreach ([
        ['08:00', PunchType::IN],
        ['12:00', PunchType::LUNCH_OUT],
        ['13:00', PunchType::LUNCH_IN],
        ['17:00', PunchType::OUT],
    ] as [$time, $type]) {
        TimeLog::create([
            'employee_id' => $this->employee->id,
            'timestamp' => Carbon::parse("{$date} {$time}"),
            'type' => $type,
            'source' => PunchSource::SELF_SERVICE,
        ]);
    }

    // ...plus 2h approved overtime. shift_type intentionally 'rest_day' to prove
    // the service overrides it to the regular multiplier.
    OvertimeRequest::create([
        'employee_id' => $this->employee->id,
        'date' => $date,
        'start_time' => $date.' 17:00:00',
        'end_time' => $date.' 19:00:00',
        'shift_type' => 'rest_day',
        'reason' => 'Rush order',
        'status' => 'approved',
        'approved_by' => $this->admin->id,
        'approved_at' => now(),
    ]);

    $sheet = $this->service->processDailyAttendance($this->employee, $date);

    expect((int) $sheet->overtime_minutes)->toBe(120);
    expect((float) $sheet->overtime_multiplier)->toBe(1.25); // regular OT, not 1.69
    // 2h × ₱75 × 1.25 = ₱187.50
    expect((float) $sheet->overtime_pay)->toBe(187.5);
    // Base ₱600 + OT ₱187.50
    expect((float) $sheet->daily_wage)->toBe(787.5);
});

it('does not mark an absence when a rest day is not worked', function () {
    $sheet = $this->service->processDailyAttendance($this->employee, $this->sunday);

    expect((bool) $sheet->is_rest_day)->toBeTrue();
    expect((bool) $sheet->is_present)->toBeFalse();
    expect($sheet->absence_type)->toBeNull();
    expect((float) $sheet->daily_wage)->toBe(0.0);
});

it('has no attendance sheet on a Sunday the employee did not work', function () {
    // Attendance sheets are created on demand from punches. With no Sunday
    // activity, the employee simply has no attendance record for that day —
    // they are not present and not absent.
    TimeLog::create([
        'employee_id' => $this->employee->id,
        'timestamp' => Carbon::parse('2026-06-20 08:00'), // Saturday, a work day
        'type' => PunchType::IN,
        'source' => PunchSource::SELF_SERVICE,
    ]);
    $this->service->processDailyAttendance($this->employee, '2026-06-20');

    // No punches were recorded on the Sunday, so nothing processed it.
    $exists = AttendanceSheet::where('employee_id', $this->employee->id)
        ->where('date', $this->sunday)
        ->exists();

    expect($exists)->toBeFalse();
    expect(AttendanceSheet::where('employee_id', $this->employee->id)->where('date', '2026-06-20')->exists())->toBeTrue();
});

it('keeps the rest-day OT request shift_type as a regular working day', function () {
    // Sanity on the resolver cleanup: a rest-day OT submission no longer records
    // the (now unused) 'rest_day' shift type.
    $this->admin->update(['employee_id' => $this->employee->id]);

    $this->actingAs($this->admin)
        ->post(route('payroll.overtime.store'), [
            'date' => $this->sunday,
            'start_time' => '17:00',
            'end_time' => '19:00',
            'reason' => 'Rush order',
        ])
        ->assertRedirect();

    $ot = OvertimeRequest::where('employee_id', $this->employee->id)->first();

    expect($ot)->not->toBeNull();
    expect($ot->shift_type)->toBe('regular_day');
});
