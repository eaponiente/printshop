<?php

use App\Models\Branch;
use App\Models\Payroll\Employee;
use App\Models\Payroll\TimeLog;
use App\Models\User;
use Illuminate\Support\Facades\Route;

beforeEach(function () {
    $this->branch = Branch::factory()->create([
        'name' => 'Test Branch',
        'latitude' => 7.1907,
        'longitude' => 125.4553,
        'geofence_radius' => 100,
    ]);

    $this->staff = User::factory()->create([
        'role' => 'staff',
        'branch_id' => $this->branch->id,
    ]);

    $this->employee = Employee::create([
        'first_name' => 'Test',
        'last_name' => 'Employee',
        'branch_id' => $this->branch->id,
        'hire_date' => now()->toDateString(),
        'current_daily_rate' => 510,
    ]);

    $this->staff->update(['employee_id' => $this->employee->id]);
});

it('rejects punch with missing type', function () {
    $response = $this->actingAs($this->staff)
        ->post(route('payroll.attendance.punch'), []);

    $response->assertSessionHasErrors('type');
    expect(TimeLog::count())->toBe(0);
});

it('rejects punch with invalid type', function () {
    $response = $this->actingAs($this->staff)
        ->post(route('payroll.attendance.punch'), [
            'type' => 'not_a_real_type',
        ]);

    $response->assertSessionHasErrors('type');
    expect(TimeLog::count())->toBe(0);
});

it('rejects punch with out-of-range latitude', function () {
    $response = $this->actingAs($this->staff)
        ->post(route('payroll.attendance.punch'), [
            'type' => 'in',
            'latitude' => 95.0,
            'longitude' => 125.4,
        ]);

    $response->assertSessionHasErrors('latitude');
});

it('rejects punch with out-of-range longitude', function () {
    $response = $this->actingAs($this->staff)
        ->post(route('payroll.attendance.punch'), [
            'type' => 'in',
            'latitude' => 7.19,
            'longitude' => 200.0,
        ]);

    $response->assertSessionHasErrors('longitude');
});

it('rejects punch with negative accuracy_meters', function () {
    $response = $this->actingAs($this->staff)
        ->post(route('payroll.attendance.punch'), [
            'type' => 'in',
            'accuracy_meters' => -5,
        ]);

    $response->assertSessionHasErrors('accuracy_meters');
});

it('returns error when user has no employee link', function () {
    $this->staff->update(['employee_id' => null]);

    $response = $this->actingAs($this->staff->fresh())
        ->post(route('payroll.attendance.punch'), [
            'type' => 'in',
        ]);

    $response->assertSessionHasErrors('error');
    expect(TimeLog::count())->toBe(0);
});

it('stores geolocation on overtime punches', function () {
    $this->actingAs($this->staff)
        ->post(route('payroll.attendance.punch'), [
            'type' => 'overtime_in',
            'latitude' => 7.1908,
            'longitude' => 125.4554,
            'accuracy_meters' => 15,
        ]);

    $log = TimeLog::where('employee_id', $this->employee->id)
        ->where('type', 'overtime_in')
        ->first();

    expect($log)->not->toBeNull();
    expect((float) $log->latitude)->toBe(7.1908);
    expect((float) $log->longitude)->toBe(125.4554);
    expect($log->accuracy_meters)->toBe(15);
    expect($log->note)->toContain('Test Branch');
});

it('ignores a client-supplied timestamp and always stores the server clock', function () {
    $serverNow = now()->setDate(2026, 9, 7)->setTime(8, 0, 0);
    $this->travelTo($serverNow);

    // The employee's device clock is ~15 days ahead — a wildly wrong client
    // timestamp must never make it into the stored punch.
    $clientTimestamp = $serverNow->copy()->addDays(15)->format('Y-m-d H:i:s');

    $this->actingAs($this->staff)
        ->post(route('payroll.attendance.punch'), [
            'type' => 'in',
            'timestamp' => $clientTimestamp,
        ]);

    $log = TimeLog::where('employee_id', $this->employee->id)->first();

    expect($log)->not->toBeNull();
    expect($log->timestamp->format('Y-m-d H:i:s'))->toBe($serverNow->format('Y-m-d H:i:s'));
    expect($log->timestamp->format('Y-m-d H:i:s'))->not->toBe($clientTimestamp);
});

it('marks same-type punches within 5 minutes as duplicates', function () {
    $this->actingAs($this->staff)
        ->post(route('payroll.attendance.punch'), ['type' => 'in']);
    $this->actingAs($this->staff)
        ->post(route('payroll.attendance.punch'), ['type' => 'in']);

    $logs = TimeLog::where('employee_id', $this->employee->id)
        ->where('type', 'in')
        ->orderBy('id')
        ->get();

    expect($logs)->toHaveCount(2);
    expect($logs[0]->duplicate_of)->toBeNull();
    expect($logs[1]->duplicate_of)->toBe($logs[0]->id);
});

it('requires authentication', function () {
    $response = $this->post(route('payroll.attendance.punch'), ['type' => 'in']);

    $response->assertRedirect();
    expect(TimeLog::count())->toBe(0);
});

it('applies throttle middleware on the punch route', function () {
    $route = collect(Route::getRoutes())
        ->first(fn ($r) => $r->getName() === 'payroll.attendance.punch');

    expect($route)->not->toBeNull();
    expect(implode(',', $route->gatherMiddleware()))->toContain('throttle:30,1');
});

it('allows admin backdating through the manual endpoint to accept overtime_in', function () {
    $admin = User::factory()->create([
        'role' => 'admin',
        'branch_id' => $this->branch->id,
    ]);

    $backdate = now()->subDays(3)->setTime(19, 0, 0);

    $response = $this->actingAs($admin)
        ->post(route('payroll.attendance.manual'), [
            'employee_id' => $this->employee->id,
            'type' => 'overtime_in',
            'timestamp' => $backdate->format('Y-m-d H:i:s'),
            'note' => 'Backdated overtime',
        ]);

    $response->assertSessionDoesntHaveErrors();

    $log = TimeLog::where('employee_id', $this->employee->id)
        ->where('type', 'overtime_in')
        ->first();

    expect($log)->not->toBeNull();
    expect($log->timestamp->format('Y-m-d H:i:s'))->toBe($backdate->format('Y-m-d H:i:s'));
});
