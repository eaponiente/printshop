<?php

use App\Models\Payroll\Holiday;
use App\Models\User;
use Carbon\Carbon;

afterEach(function () {
    Carbon::setTestNow();
});

it('orders passed holidays below upcoming ones', function () {
    Carbon::setTestNow(Carbon::parse('2026-07-19'));

    $admin = User::factory()->create(['role' => 'admin', 'branch_id' => null]);

    $past = Holiday::create(['name' => 'Past', 'date' => '2026-01-01', 'type' => 'regular', 'recurring' => false]);
    $today = Holiday::create(['name' => 'Today', 'date' => '2026-07-19', 'type' => 'special', 'recurring' => false]);
    $soon = Holiday::create(['name' => 'Soon', 'date' => '2026-12-25', 'type' => 'regular', 'recurring' => false]);

    $response = $this->actingAs($admin)->get(route('payroll.holidays.index'));

    $ids = collect($response->viewData('page')['props']['holidays']['data'])->pluck('id')->all();

    // Today counts as upcoming; the past holiday sinks to the bottom.
    expect($ids)->toBe([$today->id, $soon->id, $past->id]);
});

it('lets an admin add a holiday', function () {
    $admin = User::factory()->create(['role' => 'admin', 'branch_id' => null]);

    $this->actingAs($admin)
        ->post(route('payroll.holidays.store'), [
            'name' => 'Company Founding Day',
            'date' => '2026-09-15',
            'type' => 'special',
            'recurring' => false,
        ])
        ->assertRedirect();

    expect(Holiday::where('date', '2026-09-15')->where('type', 'special')->exists())->toBeTrue();
});

it('lets an admin update and delete a holiday', function () {
    $admin = User::factory()->create(['role' => 'admin', 'branch_id' => null]);
    $holiday = Holiday::create([
        'name' => 'Local Fiesta',
        'date' => '2026-10-05',
        'type' => 'special',
        'recurring' => false,
    ]);

    $this->actingAs($admin)
        ->put(route('payroll.holidays.update', $holiday), [
            'name' => 'Local Fiesta (Renamed)',
            'date' => '2026-10-05',
            'type' => 'regular',
            'recurring' => false,
        ])
        ->assertRedirect();

    expect($holiday->fresh()->name)->toBe('Local Fiesta (Renamed)');

    $this->actingAs($admin)
        ->delete(route('payroll.holidays.destroy', $holiday))
        ->assertRedirect();

    expect(Holiday::find($holiday->id))->toBeNull();
});

it('lets an admin view the holidays page', function () {
    $admin = User::factory()->create(['role' => 'admin', 'branch_id' => null]);

    $this->actingAs($admin)
        ->get(route('payroll.holidays.index'))
        ->assertOk();
});

it('forbids staff from adding a holiday', function () {
    $staff = User::factory()->create(['role' => 'staff', 'branch_id' => null]);

    $this->actingAs($staff)
        ->post(route('payroll.holidays.store'), [
            'name' => 'Unauthorized Holiday',
            'date' => '2026-11-15',
            'type' => 'special',
            'recurring' => false,
        ])
        ->assertForbidden();

    expect(Holiday::where('date', '2026-11-15')->exists())->toBeFalse();
});
