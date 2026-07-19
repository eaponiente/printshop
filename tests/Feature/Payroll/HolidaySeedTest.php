<?php

use App\Models\Payroll\Holiday;

it('seeds the canonical calendar for a requested year', function () {
    Holiday::seedYear(2027);

    expect(Holiday::whereYear('date', 2027)->count())->toBe(count(Holiday::defaultsForYear(2027)));
    expect(Holiday::where('name', "New Year's Day")->where('date', '2027-01-01')->exists())->toBeTrue();
});

it('places National Heroes Day on the last Monday of August for the year', function () {
    Holiday::seedYear(2027);

    // Last Monday of August 2027 is the 30th.
    $heroes = Holiday::where('name', 'National Heroes Day')->whereYear('date', 2027)->first();

    expect($heroes)->not->toBeNull();
    expect($heroes->date->toDateString())->toBe('2027-08-30');
    expect($heroes->recurring)->toBeFalse();
});

it('resolves a non-recurring holiday in the new year after seeding', function () {
    // Before seeding the new year, the movable holiday does not resolve.
    expect(Holiday::forDate('2027-08-30'))->toBeNull();

    Holiday::seedYear(2027);

    expect(Holiday::forDate('2027-08-30'))->not->toBeNull();
});

it('is idempotent', function () {
    Holiday::seedYear(2027);
    $created = Holiday::seedYear(2027);

    expect($created)->toBe(0);
});

it('resolves recurring fixed-date holidays across years without an explicit seed', function () {
    Holiday::seedYear(2026);

    // Christmas is recurring, so it matches by month/day even for an unseeded year.
    expect(Holiday::forDate('2028-12-25'))->not->toBeNull();
});
