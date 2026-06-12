<?php

namespace Payroll\Contractor\Services;

use App\Models\Branch;
use App\Models\Payroll\ContractorCashAdvance;
use App\Models\Payroll\ContractorProject;
use App\Models\Payroll\PayrollPeriod;
use App\Models\Payroll\PayrollPeriodContractorItem;
use DB;
use Payroll\Attendance\Enums\PayrollPeriodStatus;

class ContractorPayrollPeriodService
{
    public function generate(Branch $branch, string $periodStart, string $periodEnd): PayrollPeriod
    {
        return DB::transaction(function () use ($branch, $periodStart, $periodEnd) {
            $period = PayrollPeriod::create([
                'branch_id' => $branch->id,
                'period_start' => $periodStart,
                'period_end' => $periodEnd,
                'status' => PayrollPeriodStatus::DRAFT,
            ]);

            $activeProjects = ContractorProject::where('status', 'active')
                ->where('remaining_installments', '>', 0)
                ->whereHas('contractor', fn ($q) => $q->where('branch_id', $branch->id)->where('status', 'active'))
                ->with('contractor')
                ->get();

            foreach ($activeProjects as $project) {
                $this->generateItemForProject($period, $project);
            }

            return $period;
        });
    }

    private function generateItemForProject(PayrollPeriod $period, ContractorProject $project): PayrollPeriodContractorItem
    {
        $grossAmount = $project->installment_amount;
        $caDeduction = $this->computeCADeduction($project->contractor_id, $grossAmount);
        $netPay = round($grossAmount - $caDeduction, 2);

        $item = PayrollPeriodContractorItem::create([
            'payroll_period_id' => $period->id,
            'contractor_id' => $project->contractor_id,
            'project_id' => $project->id,
            'contract_amount' => $grossAmount,
            'ca_deduction' => $caDeduction,
            'net_pay' => $netPay,
        ]);

        $project->consumeInstallment();

        return $item;
    }

    private function computeCADeduction(int $contractorId, float $netReceivable): float
    {
        $activeCA = ContractorCashAdvance::where('contractor_id', $contractorId)
            ->whereIn('status', ['approved', 'unpaid'])
            ->where('remaining_balance', '>', 0)
            ->first();

        if (! $activeCA) {
            return 0;
        }

        $deduction = min((float) $activeCA->remaining_balance, $netReceivable);
        $newBalance = (float) $activeCA->remaining_balance - $deduction;

        $activeCA->update([
            'remaining_balance' => $newBalance,
            'status' => $newBalance <= 0 ? 'paid' : $activeCA->status,
        ]);

        return round($deduction, 2);
    }

    public function approve(PayrollPeriod $period, int $approvedBy): void
    {
        if ($period->status !== PayrollPeriodStatus::DRAFT) {
            throw new \RuntimeException('Only draft periods can be approved.');
        }

        $period->update([
            'status' => PayrollPeriodStatus::APPROVED,
            'approved_by' => $approvedBy,
            'approved_at' => now(),
        ]);
    }

    public function delete(PayrollPeriod $period): void
    {
        if ($period->status !== PayrollPeriodStatus::DRAFT) {
            throw new \RuntimeException('Only draft periods can be deleted.');
        }

        DB::transaction(function () use ($period) {
            $period->contractorItems()->delete();
            $period->delete();
        });
    }
}
