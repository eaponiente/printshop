<?php

namespace Database\Seeders;

use App\Models\Payroll\SssContributionBracket;
use Illuminate\Database\Seeder;

class SssBracketSeeder extends Seeder
{
    public function run(): void
    {
        $brackets = [
            ['salary_min' => 1, 'salary_max' => 4250, 'employee_percentage' => 5.00, 'employer_percentage' => 10.00, 'effective_from' => '2026-01-01'],
            ['salary_min' => 4251, 'salary_max' => 5250, 'employee_percentage' => 5.00, 'employer_percentage' => 10.00, 'effective_from' => '2026-01-01'],
            ['salary_min' => 5251, 'salary_max' => 6250, 'employee_percentage' => 5.00, 'employer_percentage' => 10.00, 'effective_from' => '2026-01-01'],
            ['salary_min' => 6251, 'salary_max' => 7250, 'employee_percentage' => 5.00, 'employer_percentage' => 10.00, 'effective_from' => '2026-01-01'],
            ['salary_min' => 7251, 'salary_max' => 8250, 'employee_percentage' => 5.00, 'employer_percentage' => 10.00, 'effective_from' => '2026-01-01'],
            ['salary_min' => 8251, 'salary_max' => 9250, 'employee_percentage' => 5.00, 'employer_percentage' => 10.00, 'effective_from' => '2026-01-01'],
            ['salary_min' => 9251, 'salary_max' => 10250, 'employee_percentage' => 5.00, 'employer_percentage' => 10.00, 'effective_from' => '2026-01-01'],
            ['salary_min' => 10251, 'salary_max' => 11250, 'employee_percentage' => 5.00, 'employer_percentage' => 10.00, 'effective_from' => '2026-01-01'],
            ['salary_min' => 11251, 'salary_max' => 12250, 'employee_percentage' => 5.00, 'employer_percentage' => 10.00, 'effective_from' => '2026-01-01'],
            ['salary_min' => 12251, 'salary_max' => 13250, 'employee_percentage' => 5.00, 'employer_percentage' => 10.00, 'effective_from' => '2026-01-01'],
            ['salary_min' => 13251, 'salary_max' => 14250, 'employee_percentage' => 5.00, 'employer_percentage' => 10.00, 'effective_from' => '2026-01-01'],
            ['salary_min' => 14251, 'salary_max' => 15250, 'employee_percentage' => 5.00, 'employer_percentage' => 10.00, 'effective_from' => '2026-01-01'],
            ['salary_min' => 15251, 'salary_max' => 16250, 'employee_percentage' => 5.00, 'employer_percentage' => 10.00, 'effective_from' => '2026-01-01'],
            ['salary_min' => 16251, 'salary_max' => 17250, 'employee_percentage' => 5.00, 'employer_percentage' => 10.00, 'effective_from' => '2026-01-01'],
            ['salary_min' => 17251, 'salary_max' => 18250, 'employee_percentage' => 5.00, 'employer_percentage' => 10.00, 'effective_from' => '2026-01-01'],
            ['salary_min' => 18251, 'salary_max' => 19250, 'employee_percentage' => 5.00, 'employer_percentage' => 10.00, 'effective_from' => '2026-01-01'],
            ['salary_min' => 19251, 'salary_max' => 20000, 'employee_percentage' => 5.00, 'employer_percentage' => 10.00, 'effective_from' => '2026-01-01'],
        ];

        foreach ($brackets as $bracket) {
            SssContributionBracket::firstOrCreate(
                ['salary_min' => $bracket['salary_min']],
                $bracket,
            );
        }
    }
}
