<?php

namespace App\Concerns;

use App\Models\Expense;
use App\Models\PurchaseOrder;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;

trait SaleFilterTrait
{
    // App\Models\Transaction.php (And similar for Expense)
    public function scopeFiltered(Builder $query, array $filters)
    {
        return $query->tap(fn($q) => $this->applyDateFilter($q, $filters))
            ->where(function ($query) use ($filters) {
                $user = auth()->user();
                $filterId = $filters['branch_id'] ?? null;

                if (!in_array($user->role, ['superadmin', 'admin'])) {
                    // Non-admins are FORCED to their branch or expenses they created
                    $query->where(function ($q) use ($user) {
                        $q->where('branch_id', $user->branch_id)
                            ->orWhere('user_id', $user->id);
                    });
                } elseif ($filterId && $filterId !== 'all') {
                    // Admins/Superadmins only get a WHERE clause if they picked a specific branch
                    $query->where('branch_id', $filterId);
                }
            });
    }

    public function scopeDateFiltered($query, array $filters)
    {
        return $query->tap(fn($q) => $this->applyDateFilter($q, $filters));
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
