<?php

use App\Models\Branch;
use App\Models\Payroll\AttendanceSheet;
use App\Models\Payroll\CorrectionRequest;
use App\Models\Payroll\CorrectionRequestItem;
use App\Models\Payroll\Employee;
use App\Models\Payroll\TimeLog;
use App\Models\User;

beforeEach(function () {
    $this->branch = Branch::factory()->create(['name' => 'Test Branch']);

    $this->superadmin = User::factory()->create([
        'branch_id' => null,
        'role' => 'superadmin',
    ]);

    $this->admin = User::factory()->create([
        'branch_id' => $this->branch->id,
        'role' => 'admin',
    ]);

    $this->staff = User::factory()->create([
        'branch_id' => $this->branch->id,
        'role' => 'staff',
    ]);

    $this->employee = Employee::create([
        'first_name' => 'Staff',
        'last_name' => 'Member',
        'hire_date' => now()->toDateString(),
        'branch_id' => $this->branch->id,
        'current_daily_rate' => 500,
    ]);

    $this->staff->update(['employee_id' => $this->employee->id]);
});

it('allows staff to submit correction with items', function () {
    $response = $this->actingAs($this->staff)
        ->post(route('payroll.corrections.store'), [
            'date' => now()->toDateString(),
            'correction_type' => 'time_adjustment',
            'reason' => 'Wrong punch times',
            'items' => [
                ['punch_type' => 'in', 'requested_time' => '08:15'],
                ['punch_type' => 'out', 'requested_time' => '17:30'],
            ],
        ]);

    $response->assertRedirect();
    $response->assertSessionHas('success');

    $correction = CorrectionRequest::where('employee_id', $this->employee->id)->first();
    expect($correction)->not->toBeNull();
    expect($correction->items()->count())->toBe(2);
    expect($correction->items()->first()->punch_type)->toBe('in');
    expect($correction->items()->first()->requested_time->format('H:i:s'))->toBe('08:15:00');
});

it('rejects correction with invalid punch_type', function () {
    $response = $this->actingAs($this->staff)
        ->post(route('payroll.corrections.store'), [
            'date' => now()->toDateString(),
            'correction_type' => 'missed_punch_in',
            'reason' => 'Forgot to punch',
            'items' => [
                ['punch_type' => 'invalid_type', 'requested_time' => '08:00'],
            ],
        ]);

    $response->assertSessionHasErrors(['items.0.punch_type']);
});

it('rejects correction with missing items array', function () {
    $response = $this->actingAs($this->staff)
        ->post(route('payroll.corrections.store'), [
            'date' => now()->toDateString(),
            'correction_type' => 'missed_punch_in',
            'reason' => 'Forgot to punch',
        ]);

    $response->assertSessionHasErrors(['items']);
});

it('rejects correction with empty items array', function () {
    $response = $this->actingAs($this->staff)
        ->post(route('payroll.corrections.store'), [
            'date' => now()->toDateString(),
            'correction_type' => 'missed_punch_in',
            'reason' => 'Forgot to punch',
            'items' => [],
        ]);

    $response->assertSessionHasErrors(['items']);
});

it('requires requested_time on each item', function () {
    $response = $this->actingAs($this->staff)
        ->post(route('payroll.corrections.store'), [
            'date' => now()->toDateString(),
            'correction_type' => 'missed_punch_in',
            'reason' => 'Forgot to punch',
            'items' => [
                ['punch_type' => 'in'],
            ],
        ]);

    $response->assertSessionHasErrors(['items.0.requested_time']);
});

it('prevents duplicate correction for same date regardless of type', function () {
    $correction = CorrectionRequest::create([
        'employee_id' => $this->employee->id,
        'date' => now()->toDateString(),
        'correction_type' => 'missed_punch_in',
        'reason' => 'First request',
        'status' => 'pending',
    ]);

    $response = $this->actingAs($this->staff)
        ->post(route('payroll.corrections.store'), [
            'date' => now()->toDateString(),
            'correction_type' => 'missed_punch_in',
            'reason' => 'Second request',
            'items' => [
                ['punch_type' => 'in', 'requested_time' => '08:00'],
            ],
        ]);

    $response->assertSessionHasErrors(['error']);
});

it('prevents correction for same date even with different type', function () {
    CorrectionRequest::create([
        'employee_id' => $this->employee->id,
        'date' => now()->toDateString(),
        'correction_type' => 'missed_punch_in',
        'reason' => 'First request',
        'status' => 'pending',
    ]);

    $response = $this->actingAs($this->staff)
        ->post(route('payroll.corrections.store'), [
            'date' => now()->toDateString(),
            'correction_type' => 'missed_punch_out',
            'reason' => 'Different type',
            'items' => [
                ['punch_type' => 'out', 'requested_time' => '17:00'],
            ],
        ]);

    $response->assertSessionHasErrors(['error']);
});

it('rejects correction with duplicate punch types in items', function () {
    $response = $this->actingAs($this->staff)
        ->post(route('payroll.corrections.store'), [
            'date' => now()->toDateString(),
            'correction_type' => 'time_adjustment',
            'reason' => 'Testing duplicates',
            'items' => [
                ['punch_type' => 'in', 'requested_time' => '08:00'],
                ['punch_type' => 'in', 'requested_time' => '09:00'],
            ],
        ]);

    $response->assertSessionHasErrors(['error']);
});

it('approve creates time_logs per item', function () {
    $correction = CorrectionRequest::create([
        'employee_id' => $this->employee->id,
        'date' => now()->toDateString(),
        'correction_type' => 'time_adjustment',
        'reason' => 'Times were wrong',
        'status' => 'pending',
    ]);

    CorrectionRequestItem::create([
        'correction_request_id' => $correction->id,
        'punch_type' => 'in',
        'requested_time' => now()->toDateString().' 08:15:00',
    ]);

    CorrectionRequestItem::create([
        'correction_request_id' => $correction->id,
        'punch_type' => 'out',
        'requested_time' => now()->toDateString().' 17:30:00',
    ]);

    $response = $this->actingAs($this->admin)
        ->post(route('payroll.corrections.approve', $correction));

    $response->assertRedirect();
    $response->assertSessionHas('success');

    $correction->refresh();
    expect($correction->status)->toBe('approved');
    expect($correction->reviewed_by)->toBe($this->admin->id);
    expect($correction->resolved_time_log_id)->not->toBeNull();

    $logs = TimeLog::where('employee_id', $this->employee->id)
        ->where('source', 'correction')
        ->get();

    expect($logs)->toHaveCount(2);
    expect($logs->first()->type->value)->toBe('in');
    expect($logs->last()->type->value)->toBe('out');
    expect($logs->first()->timestamp->format('H:i:s'))->toBe('08:15:00');
    expect($logs->last()->timestamp->format('H:i:s'))->toBe('17:30:00');

    $sheet = AttendanceSheet::where('employee_id', $this->employee->id)
        ->where('date', now()->toDateString())
        ->first();

    expect($sheet)->not->toBeNull();
    expect($sheet->is_present)->toBeTrue();
    expect($sheet->hours_worked)->toBeGreaterThan(0);
});

it('approve handles single missed_punch_in correction', function () {
    $correction = CorrectionRequest::create([
        'employee_id' => $this->employee->id,
        'date' => now()->toDateString(),
        'correction_type' => 'missed_punch_in',
        'reason' => 'Forgot to punch in',
        'status' => 'pending',
    ]);

    CorrectionRequestItem::create([
        'correction_request_id' => $correction->id,
        'punch_type' => 'in',
        'requested_time' => now()->toDateString().' 08:00:00',
    ]);

    $this->actingAs($this->admin)
        ->post(route('payroll.corrections.approve', $correction));

    $correction->refresh();
    expect($correction->status)->toBe('approved');

    $logs = TimeLog::where('employee_id', $this->employee->id)
        ->where('source', 'correction')
        ->get();

    expect($logs)->toHaveCount(1);
    expect($logs->first()->type->value)->toBe('in');

    $sheet = AttendanceSheet::where('employee_id', $this->employee->id)
        ->where('date', now()->toDateString())
        ->first();

    expect($sheet)->not->toBeNull();
    expect($sheet->is_present)->toBeTrue();
});

it('denies correction without creating time_logs', function () {
    $correction = CorrectionRequest::create([
        'employee_id' => $this->employee->id,
        'date' => now()->toDateString(),
        'correction_type' => 'missed_punch_in',
        'reason' => 'Forgot to punch',
        'status' => 'pending',
    ]);

    CorrectionRequestItem::create([
        'correction_request_id' => $correction->id,
        'punch_type' => 'in',
        'requested_time' => now()->toDateString().' 08:00:00',
    ]);

    $this->actingAs($this->admin)
        ->post(route('payroll.corrections.deny', $correction), [
            'denial_reason' => 'No proof provided',
        ]);

    $correction->refresh();
    expect($correction->status)->toBe('denied');
    expect($correction->denial_reason)->toBe('No proof provided');

    $logs = TimeLog::where('employee_id', $this->employee->id)
        ->where('source', 'correction')
        ->get();

    expect($logs)->toHaveCount(0);
});

it('index includes items relationship', function () {
    $correction = CorrectionRequest::create([
        'employee_id' => $this->employee->id,
        'date' => now()->toDateString(),
        'correction_type' => 'time_adjustment',
        'reason' => 'Test',
        'status' => 'pending',
    ]);

    CorrectionRequestItem::create([
        'correction_request_id' => $correction->id,
        'punch_type' => 'in',
        'requested_time' => now()->toDateString().' 08:15:00',
    ]);

    $response = $this->actingAs($this->admin)
        ->get(route('payroll.corrections.index'));

    $response->assertOk();

    $props = $response->inertiaProps();
    expect($props)->toHaveKey('requests');
});
