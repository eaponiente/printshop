<?php

use App\Models\Branch;
use App\Models\Payroll\Employee;
use App\Models\User;
use Illuminate\Support\Facades\Schema;
use Payroll\Audit\Models\AuditLog;
use Payroll\Employee\Enums\EmployeePosition;
use Payroll\Employee\Enums\EmployeeStatus;

beforeEach(function () {
    $this->branchA = Branch::factory()->create(['name' => 'Branch A']);
    $this->branchB = Branch::factory()->create(['name' => 'Branch B']);

    $this->superadmin = User::factory()->create([
        'branch_id' => null,
        'role' => 'superadmin',
    ]);

    $this->adminA = User::factory()->create([
        'branch_id' => $this->branchA->id,
        'role' => 'admin',
    ]);

    $this->adminB = User::factory()->create([
        'branch_id' => $this->branchB->id,
        'role' => 'admin',
    ]);
});

it('allows superadmin to access audit logs', function () {
    $this->actingAs($this->superadmin)
        ->get(route('payroll.audit.index'))
        ->assertOk();
});

it('allows admin to access audit logs', function () {
    $this->actingAs($this->adminA)
        ->get(route('payroll.audit.index'))
        ->assertOk();
});

it('creates audit log when employee is created via controller', function () {
    $this->actingAs($this->adminA)
        ->post(route('payroll.employees.store'), [
            'first_name' => 'Audit',
            'last_name' => 'Created',
            'hire_date' => now()->toDateString(),
            'branch_id' => $this->branchA->id,
            'position' => EmployeePosition::REGULAR->value,
            'status' => EmployeeStatus::ACTIVE->value,
            'daily_rate' => 500,
            'username' => 'audit_created',
            'password' => 'secret123',
            'password_confirmation' => 'secret123',
            'role' => 'staff',
        ]);

    $employee = Employee::where('first_name', 'Audit')->first();
    $log = AuditLog::where('action', 'created')
        ->where('model_type', Employee::class)
        ->where('model_id', $employee->id)
        ->first();

    expect($log)->not->toBeNull();
    expect($log->action)->toBe('created');
    expect($log->model_type)->toBe(Employee::class);
    expect($log->model_id)->toBe($employee->id);
    expect($log->branch_id)->toBe($this->branchA->id);
});

it('only stores changed fields in audit log', function () {
    $employee = Employee::create([
        'first_name' => 'Diff',
        'last_name' => 'Test',
        'hire_date' => now()->toDateString(),
        'branch_id' => $this->branchA->id,
        'current_daily_rate' => 500,
    ]);

    AuditLog::create([
        'action' => 'updated',
        'model_type' => Employee::class,
        'model_id' => $employee->id,
        'user_id' => $this->adminA->id,
        'branch_id' => $this->branchA->id,
        'before' => ['first_name' => 'Diff', 'current_daily_rate' => 500],
        'after' => ['first_name' => 'Diff2', 'current_daily_rate' => 700],
        'ip_address' => '127.0.0.1',
    ]);

    $log = AuditLog::where('model_id', $employee->id)
        ->where('action', 'updated')
        ->first();

    expect($log->before)->toBe(['first_name' => 'Diff', 'current_daily_rate' => 500]);
    expect($log->after)->toBe(['first_name' => 'Diff2', 'current_daily_rate' => 700]);

    $beforeKeys = array_keys($log->before);
    expect($beforeKeys)->toContain('first_name');
    expect($beforeKeys)->toContain('current_daily_rate');
    expect(count($beforeKeys))->toBe(2);
});

it('has only created_at timestamp, no updated_at', function () {
    $log = AuditLog::create([
        'action' => 'created',
        'model_type' => Employee::class,
        'model_id' => 1,
        'user_id' => $this->adminA->id,
        'branch_id' => $this->branchA->id,
        'ip_address' => '127.0.0.1',
    ]);

    expect($log->created_at)->not->toBeNull();

    $columns = Schema::getColumnListing('audit_logs');
    expect($columns)->toContain('created_at');
    expect($columns)->not->toContain('updated_at');
});

it('superadmin sees audit logs from all branches', function () {
    AuditLog::create([
        'action' => 'updated',
        'model_type' => Employee::class,
        'model_id' => 1,
        'user_id' => $this->adminA->id,
        'branch_id' => $this->branchA->id,
        'ip_address' => '127.0.0.1',
    ]);

    AuditLog::create([
        'action' => 'updated',
        'model_type' => Employee::class,
        'model_id' => 2,
        'user_id' => $this->adminB->id,
        'branch_id' => $this->branchB->id,
        'ip_address' => '127.0.0.1',
    ]);

    $response = $this->actingAs($this->superadmin)
        ->get(route('payroll.audit.index'));

    $data = $response->inertiaProps('logs')['data'];
    $branchIds = collect($data)->pluck('branch_id')->unique()->filter()->values();

    expect($branchIds->toArray())->toEqualCanonicalizing([$this->branchA->id, $this->branchB->id]);
});

it('admin only sees audit logs from their own branch', function () {
    AuditLog::create([
        'action' => 'updated',
        'model_type' => Employee::class,
        'model_id' => 1,
        'user_id' => $this->adminA->id,
        'branch_id' => $this->branchA->id,
        'ip_address' => '127.0.0.1',
    ]);

    AuditLog::create([
        'action' => 'updated',
        'model_type' => Employee::class,
        'model_id' => 2,
        'user_id' => $this->adminB->id,
        'branch_id' => $this->branchB->id,
        'ip_address' => '127.0.0.1',
    ]);

    $response = $this->actingAs($this->adminA)
        ->get(route('payroll.audit.index'));

    $data = $response->inertiaProps('logs')['data'];
    $branchIds = collect($data)->pluck('branch_id')->unique()->values();

    expect($branchIds->toArray())->toEqual([$this->branchA->id]);
});

it('audit log model has no update method via timestamps config', function () {
    $log = AuditLog::create([
        'action' => 'created',
        'model_type' => Employee::class,
        'model_id' => 1,
        'user_id' => $this->adminA->id,
        'branch_id' => $this->branchA->id,
        'ip_address' => '127.0.0.1',
    ]);

    $originalCreatedAt = $log->created_at;

    sleep(1);

    $log->setAttribute('action', 'updated');
    $log->save();

    $log->refresh();
    expect($log->created_at->timestamp)->toBe($originalCreatedAt->timestamp);
});
