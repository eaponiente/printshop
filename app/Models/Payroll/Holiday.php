<?php

namespace App\Models\Payroll;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
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
            $record = self::firstOrCreate(
                ['date' => $holiday['date'], 'type' => $holiday['type']->value],
                ['name' => $holiday['name'], 'recurring' => $holiday['recurring']],
            );
            if ($record->wasRecentlyCreated) {
                $created++;
            }
        }

        return $created;
    }

    public static function forDate(string $date): ?self
    {
        $calDate = Carbon::parse($date);

        return static::where(function ($query) use ($calDate, $date) {
            $query->where('date', $date);
            $query->orWhere(function ($q) use ($calDate) {
                $q->where('recurring', true)
                    ->whereMonth('date', $calDate->month)
                    ->whereDay('date', $calDate->day);
            });
        })->first();
    }

    public function isRestDay(): bool
    {
        return false;
    }
}
