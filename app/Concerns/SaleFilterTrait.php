<?php

namespace App\Concerns;

use App\Models\Expense;
use App\Models\PurchaseOrder;
use Carbon\Carbon;

trait SaleFilterTrait
{
    // App\Models\Transaction.php (And similar for Expense)
    public function scopeFiltered($query, array $filters)
    {
        return $query->tap(fn($q) => $this->applyDateFilter($q, $filters))
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
