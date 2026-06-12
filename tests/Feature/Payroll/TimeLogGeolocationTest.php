<?php

use App\Models\Branch;
use App\Models\Payroll\Employee;
use App\Models\Payroll\TimeLog;
use App\Models\User;

beforeEach(function () {
    $this->branch = Branch::factory()->create([
        'name' => 'Babak',
        'latitude' => 7.1907,
        'longitude' => 125.4553,
        'geofence_radius' => 100,
    ]);

    $this->user = User::factory()->create([
        'branch_id' => $this->branch->id,
        'role' => 'staff',
    ]);

    $this->employee = Employee::create([
        'first_name' => $this->user->first_name,
        'last_name' => $this->user->last_name,
        'hire_date' => now()->toDateString(),
        'branch_id' => $this->branch->id,
        'current_daily_rate' => 500,
    ]);

    $this->user->update(['employee_id' => $this->employee->id]);
});

it('stores geolocation on punch in', function () {
    $response = $this->actingAs($this->user)
        ->post(route('payroll.attendance.punch'), [
            'type' => 'in',
            'latitude' => 7.1908,
            'longitude' => 125.4554,
            'accuracy_meters' => 15,
        ]);

    $response->assertRedirect();

    $log = TimeLog::where('employee_id', $this->employee->id)
        ->where('type', 'in')
        ->first();

    expect($log)->not->toBeNull();
    expect((float) $log->latitude)->toBe(7.1908);
    expect((float) $log->longitude)->toBe(125.4554);
    expect($log->accuracy_meters)->toBe(15);
});

it('computes proximity note when within geofence', function () {
    // 7.1908, 125.4554 is ~18m from 7.1907, 125.4553
    $this->actingAs($this->user)
        ->post(route('payroll.attendance.punch'), [
            'type' => 'in',
            'latitude' => 7.1908,
            'longitude' => 125.4554,
            'accuracy_meters' => 15,
        ]);

    $log = TimeLog::where('employee_id', $this->employee->id)
        ->where('type', 'in')
        ->first();

    expect($log->note)->toContain('✅');
    expect($log->note)->toContain('Babak');
});

it('computes proximity warning when outside geofence', function () {
    // 7.2000, 125.4600 is ~1.2km from 7.1907, 125.4553
    $this->actingAs($this->user)
        ->post(route('payroll.attendance.punch'), [
            'type' => 'in',
            'latitude' => 7.2000,
            'longitude' => 125.4600,
            'accuracy_meters' => 20,
        ]);

    $log = TimeLog::where('employee_id', $this->employee->id)
        ->where('type', 'in')
        ->first();

    expect($log->note)->toContain('⚠️');
    expect($log->note)->toContain('Babak');
});

it('does not store geolocation for lunch punches', function () {
    $this->actingAs($this->user)
        ->post(route('payroll.attendance.punch'), [
            'type' => 'lunch_out',
            'latitude' => 7.1908,
            'longitude' => 125.4554,
            'accuracy_meters' => 15,
        ]);

    $log = TimeLog::where('employee_id', $this->employee->id)
        ->where('type', 'lunch_out')
        ->first();

    expect($log)->not->toBeNull();
    expect($log->latitude)->toBeNull();
    expect($log->longitude)->toBeNull();
});

it('allows punch without geolocation when permission denied', function () {
    $this->actingAs($this->user)
        ->post(route('payroll.attendance.punch'), [
            'type' => 'in',
        ]);

    $log = TimeLog::where('employee_id', $this->employee->id)
        ->where('type', 'in')
        ->first();

    expect($log)->not->toBeNull();
    expect($log->latitude)->toBeNull();
    expect($log->longitude)->toBeNull();
    expect($log->note)->toBe('📍 Location not provided');
});

it('stores location without proximity check when branch has no coordinates', function () {
    $this->branch->update(['latitude' => null, 'longitude' => null]);

    $this->actingAs($this->user)
        ->post(route('payroll.attendance.punch'), [
            'type' => 'in',
            'latitude' => 7.1908,
            'longitude' => 125.4554,
            'accuracy_meters' => 15,
        ]);

    $log = TimeLog::where('employee_id', $this->employee->id)
        ->where('type', 'in')
        ->first();

    expect($log->latitude)->not->toBeNull();
    expect($log->note)->toBe('📍 Location recorded. Branch coordinates not set.');
});

it('uses custom geofence radius per branch', function () {
    $this->branch->update(['geofence_radius' => 2000]);

    // 7.2000, 125.4600 is ~1.2km — within 2000m radius
    $this->actingAs($this->user)
        ->post(route('payroll.attendance.punch'), [
            'type' => 'in',
            'latitude' => 7.2000,
            'longitude' => 125.4600,
            'accuracy_meters' => 20,
        ]);

    $log = TimeLog::where('employee_id', $this->employee->id)
        ->where('type', 'in')
        ->first();

    expect($log->note)->toContain('✅');
});
