<?php

namespace App\Console\Commands;

use App\Models\Payroll\Holiday;
use Illuminate\Console\Command;

class SeedHolidays extends Command
{
    protected $signature = 'holidays:seed {year? : Target year (defaults to the current year)}';

    protected $description = 'Seed the canonical Philippine holiday calendar for a year, including the movable National Heroes Day. Idempotent.';

    public function handle(): int
    {
        $year = (int) ($this->argument('year') ?? now()->year);

        if ($year < 2000 || $year > 2100) {
            $this->error("Refusing to seed an implausible year: {$year}.");

            return self::FAILURE;
        }

        $created = Holiday::seedYear($year);

        $this->info("Holidays for {$year}: {$created} created, ".(count(Holiday::defaultsForYear($year)) - $created).' already present.');
        $this->warn('Reminder: proclamation-based movable holidays (Eid\'l Fitr, Eid\'l Adha, Chinese New Year) must be added manually once proclaimed.');

        return self::SUCCESS;
    }
}
