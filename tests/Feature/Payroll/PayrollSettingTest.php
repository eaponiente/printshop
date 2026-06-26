<?php

use App\Models\Branch;
use App\Models\Payroll\PayrollSetting;
use App\Models\User;
use Payroll\Audit\Models\AuditLog;

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
});

it('allows superadmin to view payroll settings', function () {
    $response = $this->actingAs($this->superadmin)
        ->get('/payroll/settings');

    $response->assertOk();
});

it('blocks admin from viewing payroll settings', function () {
    $response = $this->actingAs($this->admin)
        ->get('/payroll/settings');

    $response->assertForbidden();
});

it('blocks staff from viewing payroll settings', function () {
    $response = $this->actingAs($this->staff)
        ->get('/payroll/settings');

    $response->assertForbidden();
});

it('allows superadmin to update settings and creates audit log', function () {
    AuditLog::query()->delete();

    $setting = PayrollSetting::where('key', 'late_deduction_per_minute')->first();
    expect($setting->value)->toBe('10');

    $response = $this->actingAs($this->superadmin)
        ->put('/payroll/settings', [
            'late_deduction_per_minute' => '7',
            'late_deduction_threshold_minutes' => '15',
        ]);

    $response->assertRedirect();

    $setting->refresh();
    expect($setting->value)->toBe('7');

    $threshold = PayrollSetting::where('key', 'late_deduction_threshold_minutes')->first();
    expect($threshold->value)->toBe('15');

    $logs = AuditLog::where('model_type', PayrollSetting::class)->get();
    expect($logs)->toHaveCount(2);
});

it('blocks admin from updating settings', function () {
    $response = $this->actingAs($this->admin)
        ->put('/payroll/settings', [
            'late_deduction_per_minute' => '7',
            'late_deduction_threshold_minutes' => '15',
        ]);

    $response->assertForbidden();
});

it('does not create audit log for unchanged values', function () {
    AuditLog::query()->delete();

    $response = $this->actingAs($this->superadmin)
        ->put('/payroll/settings', [
            'late_deduction_per_minute' => '10',
            'late_deduction_threshold_minutes' => '20',
        ]);

    $response->assertRedirect();

    $logs = AuditLog::where('model_type', PayrollSetting::class)->get();
    expect($logs)->toHaveCount(0);
});

it('validates numeric input', function () {
    $response = $this->actingAs($this->superadmin)
        ->put('/payroll/settings', [
            'late_deduction_per_minute' => 'abc',
            'late_deduction_threshold_minutes' => '-5',
        ]);

    $response->assertSessionHasErrors([
        'late_deduction_per_minute',
        'late_deduction_threshold_minutes',
    ]);
});
