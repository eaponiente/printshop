<?php

namespace App\Models\Payroll;

use App\Models\Branch;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Payroll\Attendance\Enums\HolidayType;

class Holiday extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'date' => 'date:Y-m-d',
            'type' => HolidayType::class,
            'recurring' => 'boolean',
        ];
    }

    /**
     * NOTE: deleting a Branch cascades away its `branch_holiday` pivot rows
     * (see the migration). A holiday whose ONLY scope was that branch then
     * silently becomes nationwide (no pivot rows left = isNationwide()).
     * That's accepted as-is — branch deletion is rare/admin-only — but worth
     * knowing if a holiday appears to "spread" after a branch is removed.
     */
    public function branches(): BelongsToMany
    {
        return $this->belongsToMany(Branch::class, 'branch_holiday')->withTimestamps();
    }

    /**
     * A holiday with no pivot rows applies nationwide. Callers must eager-load
     * `branches` (e.g. `with('branches')`) before calling this — otherwise it
     * lazily queries per holiday.
     */
    public function isNationwide(): bool
    {
        return $this->branches->isEmpty();
    }

    /**
     * In-memory version of the branch-scope rule below, for callers that
     * already hold a loaded Holiday (with `branches` eager-loaded) instead of
     * building a query — e.g. PayrollPeriodService::resolveHolidayDates(),
     * which batches candidates once and then filters them in a date loop.
     * `$branchId = null` again means "nationwide only", matching forDate().
     */
    public function appliesToBranch(?int $branchId): bool
    {
        return $this->isNationwide() || ($branchId !== null && $this->branches->contains('id', $branchId));
    }

    /**
     * Query-builder version of the same "nationwide OR this branch" rule,
     * shared by forDate() so the predicate can't drift between call sites.
     * Named to avoid Eloquent's `scope*` local-scope auto-registration.
     */
    public static function applyBranchScope($query, ?int $branchId)
    {
        $query->whereDoesntHave('branches');
        if ($branchId !== null) {
            $query->orWhereHas('branches', fn ($b) => $b->where('branches.id', $branchId));
        }

        return $query;
    }

    /**
     * Canonical Philippine holiday calendar for a given year.
     *
     * Fixed-date holidays are marked recurring (they match by month+day, so a
     * year that was never explicitly seeded still resolves). National Heroes Day
     * is the last Monday of August — a movable date — so it is emitted as a
     * concrete, non-recurring row for the requested year.
     *
     * Proclamation-based movable holidays (Eid'l Fitr, Eid'l Adha, Chinese New
     * Year) are intentionally omitted: their dates are announced yearly and must
     * be entered manually once proclaimed.
     *
     * @return array<int, array{name: string, date: string, type: HolidayType, recurring: bool}>
     */
    public static function defaultsForYear(int $year): array
    {
        $pad = fn (int $m, int $d) => sprintf('%04d-%02d-%02d', $year, $m, $d);
        $nationalHeroes = Carbon::create($year, 8, 1)->lastOfMonth(Carbon::MONDAY)->toDateString();

        return [
            ['name' => "New Year's Day", 'date' => $pad(1, 1), 'type' => HolidayType::REGULAR, 'recurring' => true],
            ['name' => 'EDSA People Power Anniversary', 'date' => $pad(2, 25), 'type' => HolidayType::SPECIAL, 'recurring' => true],
            ['name' => 'Araw ng Kagitingan', 'date' => $pad(4, 9), 'type' => HolidayType::REGULAR, 'recurring' => true],
            ['name' => 'Labor Day', 'date' => $pad(5, 1), 'type' => HolidayType::REGULAR, 'recurring' => true],
            ['name' => 'Independence Day', 'date' => $pad(6, 12), 'type' => HolidayType::REGULAR, 'recurring' => true],
            ['name' => 'Ninoy Aquino Day', 'date' => $pad(8, 21), 'type' => HolidayType::SPECIAL, 'recurring' => true],
            ['name' => 'National Heroes Day', 'date' => $nationalHeroes, 'type' => HolidayType::REGULAR, 'recurring' => false],
            ['name' => "All Saints' Day", 'date' => $pad(11, 1), 'type' => HolidayType::SPECIAL, 'recurring' => true],
            ['name' => 'Bonifacio Day', 'date' => $pad(11, 30), 'type' => HolidayType::REGULAR, 'recurring' => true],
            ['name' => 'Feast of the Immaculate Conception', 'date' => $pad(12, 8), 'type' => HolidayType::SPECIAL, 'recurring' => true],
            ['name' => 'Christmas Day', 'date' => $pad(12, 25), 'type' => HolidayType::REGULAR, 'recurring' => true],
            ['name' => 'Rizal Day', 'date' => $pad(12, 30), 'type' => HolidayType::REGULAR, 'recurring' => true],
            ['name' => 'Last Day of the Year', 'date' => $pad(12, 31), 'type' => HolidayType::SPECIAL, 'recurring' => true],
        ];
    }

    /**
     * Idempotently persist the canonical calendar for a year. Returns the number
     * of holidays newly created (existing ones are left untouched).
     */
    public static function seedYear(int $year): int
    {
        $created = 0;
        foreach (self::defaultsForYear($year) as $holiday) {
            // Constrained to nationwide-only rows: without whereDoesntHave, a
            // branch-local holiday sharing the same date+type would satisfy
            // firstOrCreate's match and silently skip creating the national one.
            $record = self::whereDoesntHave('branches')->firstOrCreate(
                ['date' => $holiday['date'], 'type' => $holiday['type']->value],
                ['name' => $holiday['name'], 'recurring' => $holiday['recurring']],
            );
            if ($record->wasRecentlyCreated) {
                $created++;
            }
        }

        return $created;
    }

    /**
     * Resolve the holiday in effect for a given date at a given branch.
     *
     * `$branchId = null` means "nationwide only" — it is NEVER "no filter".
     * The branch predicate is always applied, so an employee with no branch
     * never inherits another city's branch-local holiday. (In production
     * `employees.branch_id` is NOT NULL, so both call sites always pass an
     * int — the null path only matters for tests/future callers.)
     *
     * More than one Holiday row can match the same date now that holidays can
     * be branch-scoped (e.g. a nationwide regular holiday colliding with a
     * branch-local special one). The tiebreak, in order:
     *   1. REGULAR before SPECIAL — regular pays 200%/260% vs 130%/150%, and
     *      Philippine practice is that the regular holiday governs, so the
     *      employee is never shortchanged by resolving to the lesser one.
     *   2. Branch-scoped before nationwide — the more specific declaration
     *      wins when both are the same type.
     *   3. Exact date before recurring — a concrete row entered for this year
     *      outranks a generic yearly (month+day) rule.
     *   4. `id ASC` as the final stable tiebreak.
     * This picks exactly ONE holiday; it deliberately does not implement
     * "double holiday" pay (regular + special stacking to 300%) — that's
     * unchanged from pre-branch-scoping behavior.
     */
    public static function forDate(string $date, ?int $branchId = null): ?self
    {
        $calDate = Carbon::parse($date);

        return static::query()
            ->where(function ($q) use ($calDate, $date) {
                $q->where('date', $date)
                    ->orWhere(function ($r) use ($calDate) {
                        $r->where('recurring', true)
                            ->whereMonth('date', $calDate->month)
                            ->whereDay('date', $calDate->day);
                    });
            })
            ->where(fn ($q) => static::applyBranchScope($q, $branchId))
            ->orderByRaw('CASE WHEN type = ? THEN 0 ELSE 1 END', [HolidayType::REGULAR->value])
            ->orderByRaw('(select count(*) from branch_holiday where branch_holiday.holiday_id = holidays.id) DESC')
            ->orderByRaw('CASE WHEN date = ? THEN 0 ELSE 1 END', [$date])
            ->orderBy('id')
            ->first();
    }

    public function isRestDay(): bool
    {
        return false;
    }
}
