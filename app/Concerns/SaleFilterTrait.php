<?php

namespace App\Concerns;

use App\Models\Expense;
use Carbon\Carbon;

trait SaleFilterTrait
{
    // App\Models\Transaction.php (And similar for Expense)
    public function scopeFiltered($query, array $filters)
    {
        return $query->tap(fn ($q) => $this->applyDateFilter($q, $filters))
            // ->when($filters['branch_id'] ?? null, fn ($q, $id) => $id !== 'all' ? $q->where('branch_id', $id) : $q)
            ->where(function ($query) use ($filters) {
                $user = auth()->user();
                $filterId = $filters['branch_id'] ?? null;

                if ($user->role !== 'superadmin') {
                    // Non-admins are FORCED to their branch, regardless of the filter
                    $query->where('branch_id', $user->branch_id);
                } elseif ($filterId && $filterId !== 'all') {
                    // Superadmins only get a WHERE clause if they picked a specific branch
                    $query->where('branch_id', $filterId);
                }
            });
    }

    private function applyDateFilter($query, $filters)
    {
        $date = $filters['date'] ?? now()->toDateString();
        $column = $query->getModel() instanceof Expense ? 'expense_date' : 'transaction_date';

        match ($filters['mode'] ?? 'daily') {
            'daily' => $query->whereDate($column, $date),
            'weekly' => $query->whereRaw("YEARWEEK($column, 1) = ?", [str_replace('-W', '', $date)]),
            'monthly' => $query->whereMonth($column, Carbon::parse($date)->month)
                ->whereYear($column, Carbon::parse($date)->year),
            default => $query->whereDate($column, now()->toDateString()),
        };
    }
}
