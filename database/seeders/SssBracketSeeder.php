<?php

namespace Database\Seeders;

use App\Models\Payroll\SssContributionBracket;
use Illuminate\Database\Seeder;

// SSS Circular No. 2024-006 — Schedule of SSS Contributions effective January 2025
// employee_contribution and employer_contribution are monthly fixed amounts.
// employer_contribution includes Regular SS (employer share) + MPF (employer share) + EC.
// The payroll service divides by 4 to get the weekly deduction per payroll period.
class SssBracketSeeder extends Seeder
{
    public function run(): void
    {
        SssContributionBracket::truncate();

        $brackets = [];

        // Regular SS brackets: MSC 5,000 – 20,000 (step 500)
        // EC = ₱10 for MSC < 15,000; ₱30 for MSC ≥ 15,000
        $salaryMins = array_merge([0], range(5250, 19750, 500));
        $mscValues = range(5000, 20000, 500);

        foreach ($mscValues as $i => $msc) {
            $salaryMin = $salaryMins[$i];
            $salaryMax = isset($salaryMins[$i + 1]) ? ($salaryMins[$i + 1] - 0.01) : 20249.99;
            $ec = $msc >= 15000 ? 30 : 10;

            $brackets[] = [
                'salary_min' => $salaryMin,
                'salary_max' => $salaryMax,
                'employee_percentage' => 5.00,
                'employer_percentage' => 10.00,
                'employee_contribution' => $msc * 0.05,
                'employer_contribution' => $msc * 0.10 + $ec,
                'effective_from' => '2025-01-01',
            ];
        }

        // MPF brackets: salary 20,250 – 34,750+ (step 500)
        // Regular SS is capped at MSC 20,000; MPF covers the remainder up to 35,000.
        // EC stays at ₱30.
        $mpfSalaryMins = range(20250, 34750, 500);

        foreach ($mpfSalaryMins as $i => $salaryMin) {
            $mpf = ($i + 1) * 500;
            $salaryMax = $salaryMin < 34750 ? ($salaryMin + 499.99) : null;

            $brackets[] = [
                'salary_min' => $salaryMin,
                'salary_max' => $salaryMax,
                'employee_percentage' => 5.00,
                'employer_percentage' => 10.00,
                'employee_contribution' => 1000 + $mpf * 0.05,
                'employer_contribution' => 2000 + $mpf * 0.10 + 30,
                'effective_from' => '2025-01-01',
            ];
        }

        SssContributionBracket::insert($brackets);
    }
}
