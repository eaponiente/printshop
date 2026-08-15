<?php

namespace Database\Seeders;

use App\Models\Payroll\Holiday;
use Illuminate\Database\Seeder;
use Payroll\Attendance\Enums\HolidayType;

class HolidaySeeder extends Seeder
{
    public function run(): void
    {
        $holidays = [
            ['name' => "New Year's Day", 'date' => '2026-01-01', 'type' => HolidayType::REGULAR, 'recurring' => true],
            ['name' => 'EDSA People Power Anniversary', 'date' => '2026-02-25', 'type' => HolidayType::SPECIAL, 'recurring' => true],
            ['name' => 'Araw ng Kagitingan', 'date' => '2026-04-09', 'type' => HolidayType::REGULAR, 'recurring' => true],
            ['name' => 'Labor Day', 'date' => '2026-05-01', 'type' => HolidayType::REGULAR, 'recurring' => true],
            ['name' => 'Independence Day', 'date' => '2026-06-12', 'type' => HolidayType::REGULAR, 'recurring' => true],
            ['name' => 'Ninoy Aquino Day', 'date' => '2026-08-21', 'type' => HolidayType::SPECIAL, 'recurring' => true],
            ['name' => 'National Heroes Day', 'date' => '2026-08-25', 'type' => HolidayType::REGULAR, 'recurring' => false],
            ['name' => "All Saints' Day", 'date' => '2026-11-01', 'type' => HolidayType::SPECIAL, 'recurring' => true],
            ['name' => 'Bonifacio Day', 'date' => '2026-11-30', 'type' => HolidayType::REGULAR, 'recurring' => true],
            ['name' => 'Feast of the Immaculate Conception', 'date' => '2026-12-08', 'type' => HolidayType::SPECIAL, 'recurring' => true],
            ['name' => 'Christmas Day', 'date' => '2026-12-25', 'type' => HolidayType::REGULAR, 'recurring' => true],
            ['name' => 'Rizal Day', 'date' => '2026-12-30', 'type' => HolidayType::REGULAR, 'recurring' => true],
            ['name' => 'Last Day of the Year', 'date' => '2026-12-31', 'type' => HolidayType::SPECIAL, 'recurring' => true],
        ];

        foreach ($holidays as $holiday) {
            // Constrained to nationwide-only rows so a branch-local holiday
            // sharing the same date+type doesn't get mistaken for this one.
            Holiday::whereDoesntHave('branches')->firstOrCreate(
                [
                    'date' => $holiday['date'],
                    'type' => $holiday['type']->value,
                ],
                [
                    'name' => $holiday['name'],
                    'recurring' => $holiday['recurring'],
                ]
            );
        }
    }
}
