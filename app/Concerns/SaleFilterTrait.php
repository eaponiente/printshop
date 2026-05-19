<?php

namespace App\Concerns;

use App\Models\Expense;
use App\Models\PurchaseOrder;
use Carbon\Carbon;

trait SaleFilterTrait
{
    public function scopeDateFiltered($query, array $filters)
    {
        return $query->tap(fn ($q) => $this->applyDateFilter($q, $filters));
    }

    private function applyDateFilter($query, $filters)
    {
        $date = $filters['date'] ?? now()->toDateString();

        $column = match (true) {
            $query->getModel() instanceof Expense => 'expense_date',
            $query->getModel() instanceof PurchaseOrder => 'due_at',
            default => 'transaction_date',
        };

        match ($filters['mode'] ?? 'daily') {
            'daily' => $query->whereDate($column, $date),
            'weekly' => (function () use ($query, $column, $date) {
                $start = Carbon::parse($date)->startOfWeek();
                $end = Carbon::parse($date)->endOfWeek();
                $query->whereBetween($column, [$start, $end]);
            })(),
            'monthly' => $query->whereMonth($column, Carbon::parse($date)->month)
                ->whereYear($column, Carbon::parse($date)->year),
            'yearly' => $query->whereYear($column, $date), // Added yearly
            default => $query->whereDate($column, now()->toDateString()),
        };
    }
}
