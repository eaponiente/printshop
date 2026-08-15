<?php

use App\Models\Branch;
use App\Models\Payroll\Holiday;
use App\Models\User;
use Payroll\Attendance\Enums\HolidayType;
use Payroll\Audit\Models\AuditLog;

beforeEach(function () {
    $this->branchA = Branch::factory()->create(['name' => 'Branch A']);
    $this->branchB = Branch::factory()->create(['name' => 'Branch B']);

    $this->superadmin = User::factory()->create([
        'role' => 'superadmin',
        'branch_id' => null,
    ]);

    $this->admin = User::factory()->create([
        'role' => 'admin',
        'branch_id' => $this->branchA->id,
    ]);

    $this->staff = User::factory()->create([
        'role' => 'staff',
        'branch_id' => $this->branchA->id,
    ]);
});

// ──────────── Resolution ────────────

it('resolves a nationwide holiday for any branch and for null', function () {
    $holiday = Holiday::create([
        'name' => 'Nationwide Special',
        'date' => '2026-09-15',
        'type' => HolidayType::SPECIAL,
    ]);

    expect(Holiday::forDate('2026-09-15', $this->branchA->id)?->id)->toBe($holiday->id);
    expect(Holiday::forDate('2026-09-15', $this->branchB->id)?->id)->toBe($holiday->id);
    expect(Holiday::forDate('2026-09-15')?->id)->toBe($holiday->id);
});

it('resolves a branch-scoped special holiday only for its branch', function () {
    $holiday = Holiday::create([
        'name' => 'Branch A Fiesta',
        'date' => '2026-09-20',
        'type' => HolidayType::SPECIAL,
    ]);
    $holiday->branches()->sync([$this->branchA->id]);

    expect(Holiday::forDate('2026-09-20', $this->branchA->id)?->id)->toBe($holiday->id);
    expect(Holiday::forDate('2026-09-20', $this->branchB->id))->toBeNull();
    expect(Holiday::forDate('2026-09-20'))->toBeNull();
});

it('resolves a recurring branch-scoped special by month/day in a later, unseeded year', function () {
    $holiday = Holiday::create([
        'name' => 'Branch A Founding Day',
        'date' => '2026-09-20',
        'type' => HolidayType::SPECIAL,
        'recurring' => true,
    ]);
    $holiday->branches()->sync([$this->branchA->id]);

    expect(Holiday::forDate('2029-09-20', $this->branchA->id)?->id)->toBe($holiday->id);
    expect(Holiday::forDate('2029-09-20', $this->branchB->id))->toBeNull();
});

// ──────────── Tiebreak ────────────

it('tiebreak 1: regular outranks special regardless of branch scope', function () {
    $regular = Holiday::create([
        'name' => 'Nationwide Regular',
        'date' => '2026-10-05',
        'type' => HolidayType::REGULAR,
    ]);
    $special = Holiday::create([
        'name' => 'Branch A Special',
        'date' => '2026-10-05',
        'type' => HolidayType::SPECIAL,
    ]);
    $special->branches()->sync([$this->branchA->id]);

    $resolved = Holiday::forDate('2026-10-05', $this->branchA->id);

    expect($resolved?->id)->toBe($regular->id);
});

it('tiebreak 2: branch-scoped outranks nationwide for the same type', function () {
    Holiday::create([
        'name' => 'Nationwide Special',
        'date' => '2026-10-12',
        'type' => HolidayType::SPECIAL,
    ]);
    $scoped = Holiday::create([
        'name' => 'Branch A Special',
        'date' => '2026-10-12',
        'type' => HolidayType::SPECIAL,
    ]);
    $scoped->branches()->sync([$this->branchA->id]);

    $resolved = Holiday::forDate('2026-10-12', $this->branchA->id);

    expect($resolved?->id)->toBe($scoped->id);
});

it('tiebreak 3: exact-date outranks recurring for the same type and scope', function () {
    Holiday::create([
        'name' => 'Recurring Special',
        'date' => '2020-11-03', // matches by month/day only, regardless of year
        'type' => HolidayType::SPECIAL,
        'recurring' => true,
    ]);
    $exact = Holiday::create([
        'name' => 'Exact Date Special',
        'date' => '2026-11-03',
        'type' => HolidayType::SPECIAL,
        'recurring' => false,
    ]);

    $resolved = Holiday::forDate('2026-11-03');

    expect($resolved?->id)->toBe($exact->id);
});

it('tiebreak 4: id ASC is the deterministic final tiebreak', function () {
    $lower = Holiday::create([
        'name' => 'Tie A',
        'date' => '2026-11-10',
        'type' => HolidayType::SPECIAL,
    ]);
    Holiday::create([
        'name' => 'Tie B',
        'date' => '2026-11-10',
        'type' => HolidayType::SPECIAL,
    ]);

    $resolvedOnce = Holiday::forDate('2026-11-10');
    $resolvedTwice = Holiday::forDate('2026-11-10');

    expect($resolvedOnce?->id)->toBe($lower->id);
    expect($resolvedTwice?->id)->toBe($lower->id);
});

// ──────────── CRUD / Validation ────────────

it('rejects a regular holiday with non-empty branch_ids', function () {
    $response = $this->actingAs($this->admin)->post(route('payroll.holidays.store'), [
        'name' => 'Bad Regular',
        'date' => '2026-12-01',
        'type' => 'regular',
        'branch_ids' => [$this->branchA->id],
    ]);

    $response->assertSessionHasErrors('branch_ids');
    expect(Holiday::count())->toBe(0);
});

it('accepts a regular holiday with empty branch_ids', function () {
    $response = $this->actingAs($this->admin)->post(route('payroll.holidays.store'), [
        'name' => 'Good Regular',
        'date' => '2026-12-01',
        'type' => 'regular',
        'branch_ids' => [],
    ]);

    $response->assertSessionHasNoErrors();
    expect(Holiday::where('name', 'Good Regular')->exists())->toBeTrue();
});

it('creates pivot rows for a branch-scoped special holiday', function () {
    $this->actingAs($this->admin)->post(route('payroll.holidays.store'), [
        'name' => 'Two Branch Special',
        'date' => '2026-12-05',
        'type' => 'special',
        'branch_ids' => [$this->branchA->id, $this->branchB->id],
    ])->assertSessionHasNoErrors();

    $holiday = Holiday::where('name', 'Two Branch Special')->first();
    expect($holiday)->not->toBeNull();
    $this->assertDatabaseHas('branch_holiday', ['holiday_id' => $holiday->id, 'branch_id' => $this->branchA->id]);
    $this->assertDatabaseHas('branch_holiday', ['holiday_id' => $holiday->id, 'branch_id' => $this->branchB->id]);
});

it('removes a branch pivot row when narrowing scope on update', function () {
    $holiday = Holiday::create([
        'name' => 'Narrowing Special',
        'date' => '2026-12-10',
        'type' => HolidayType::SPECIAL,
    ]);
    $holiday->branches()->sync([$this->branchA->id, $this->branchB->id]);

    $this->actingAs($this->admin)->put(route('payroll.holidays.update', $holiday), [
        'name' => 'Narrowing Special',
        'date' => '2026-12-10',
        'type' => 'special',
        'branch_ids' => [$this->branchA->id],
    ])->assertSessionHasNoErrors();

    $this->assertDatabaseHas('branch_holiday', ['holiday_id' => $holiday->id, 'branch_id' => $this->branchA->id]);
    $this->assertDatabaseMissing('branch_holiday', ['holiday_id' => $holiday->id, 'branch_id' => $this->branchB->id]);
});

it('rejects converting special to regular while branch_ids is still non-empty', function () {
    $holiday = Holiday::create([
        'name' => 'Convert Me',
        'date' => '2026-12-15',
        'type' => HolidayType::SPECIAL,
    ]);
    $holiday->branches()->sync([$this->branchA->id]);

    $response = $this->actingAs($this->admin)->put(route('payroll.holidays.update', $holiday), [
        'name' => 'Convert Me',
        'date' => '2026-12-15',
        'type' => 'regular',
        'branch_ids' => [$this->branchA->id],
    ]);

    $response->assertSessionHasErrors('branch_ids');
    expect($holiday->fresh()->type)->toBe(HolidayType::SPECIAL);
    $this->assertDatabaseHas('branch_holiday', ['holiday_id' => $holiday->id, 'branch_id' => $this->branchA->id]);
});

it('allows converting special to regular once branch_ids is cleared, and clears pivot rows', function () {
    $holiday = Holiday::create([
        'name' => 'Convert Me',
        'date' => '2026-12-15',
        'type' => HolidayType::SPECIAL,
    ]);
    $holiday->branches()->sync([$this->branchA->id]);

    $this->actingAs($this->admin)->put(route('payroll.holidays.update', $holiday), [
        'name' => 'Convert Me',
        'date' => '2026-12-15',
        'type' => 'regular',
        'branch_ids' => [],
    ])->assertSessionHasNoErrors();

    $holiday->refresh();
    expect($holiday->type)->toBe(HolidayType::REGULAR);
    $this->assertDatabaseMissing('branch_holiday', ['holiday_id' => $holiday->id]);
});

it('rejects a non-existent branch id in branch_ids', function () {
    $response = $this->actingAs($this->admin)->post(route('payroll.holidays.store'), [
        'name' => 'Bad Branch',
        'date' => '2026-12-20',
        'type' => 'special',
        'branch_ids' => [999999],
    ]);

    $response->assertSessionHasErrors('branch_ids.0');
    expect(Holiday::count())->toBe(0);
});

it('cascades pivot rows away when a holiday is deleted', function () {
    $holiday = Holiday::create([
        'name' => 'Delete Me',
        'date' => '2026-12-25',
        'type' => HolidayType::SPECIAL,
    ]);
    $holiday->branches()->sync([$this->branchA->id]);

    $this->actingAs($this->admin)->delete(route('payroll.holidays.destroy', $holiday))
        ->assertSessionHasNoErrors();

    $this->assertDatabaseMissing('branch_holiday', ['holiday_id' => $holiday->id]);
    expect(Holiday::find($holiday->id))->toBeNull();
});

it('denies staff from creating a holiday, before validation even runs', function () {
    $response = $this->actingAs($this->staff)->post(route('payroll.holidays.store'), [
        'name' => 'Nope',
        'date' => 'not-a-date', // also invalid, to prove authorize() wins first
        'type' => 'special',
        'branch_ids' => [999999], // also invalid
    ]);

    $response->assertForbidden();
    expect(Holiday::count())->toBe(0);
});

it('writes audit rows for holiday create, update, and delete', function () {
    $this->actingAs($this->admin)->post(route('payroll.holidays.store'), [
        'name' => 'Audited Holiday',
        'date' => '2026-12-28',
        'type' => 'special',
        'branch_ids' => [$this->branchA->id],
    ]);

    $holiday = Holiday::where('name', 'Audited Holiday')->first();
    expect($holiday)->not->toBeNull();
    expect(
        AuditLog::where('action', 'holiday_created')
            ->where('model_type', Holiday::class)
            ->where('model_id', $holiday->id)
            ->exists()
    )->toBeTrue();

    $this->actingAs($this->admin)->put(route('payroll.holidays.update', $holiday), [
        'name' => 'Audited Holiday Updated',
        'date' => '2026-12-28',
        'type' => 'special',
        'branch_ids' => [$this->branchA->id],
    ]);

    expect(
        AuditLog::where('action', 'holiday_updated')
            ->where('model_type', Holiday::class)
            ->where('model_id', $holiday->id)
            ->exists()
    )->toBeTrue();

    $this->actingAs($this->admin)->delete(route('payroll.holidays.destroy', $holiday));

    expect(
        AuditLog::where('action', 'holiday_deleted')
            ->where('model_type', Holiday::class)
            ->where('model_id', $holiday->id)
            ->exists()
    )->toBeTrue();
});

it('seedYear still creates the nationwide All Saints Day when a branch-local special already sits on the same date', function () {
    $local = Holiday::create([
        'name' => 'Branch A Local Nov 1 Special',
        'date' => '2027-11-01',
        'type' => HolidayType::SPECIAL,
    ]);
    $local->branches()->sync([$this->branchA->id]);

    Holiday::seedYear(2027);

    $nationwide = Holiday::where('name', "All Saints' Day")->where('date', '2027-11-01')->first();
    expect($nationwide)->not->toBeNull();
    expect($nationwide->id)->not->toBe($local->id);
    expect(Holiday::where('date', '2027-11-01')->count())->toBe(2);
});
