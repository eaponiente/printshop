<?php

namespace App\Models;

use App\Concerns\SaleFilterTrait;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Expense extends Model
{
    use HasFactory;
    use SaleFilterTrait;

    protected $table = 'expenses';

    protected $guarded = ['id'];

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function scopeBranchFilters($query, array $filters)
    {
        $query->when($filters['branch_id'] ?? null, function ($query, $branchId) {
            if ($branchId !== 'all') {
                $query->where('branch_id', $branchId);
            }
        });
    }

    public function scopeSalesBranchFilters($query, array $filters)
    {
        $user = auth()->user();
        $filterId = $filters['branch_id'] ?? null;

        // 1. Superadmin Logic: See everything, but allow filtering by branch
        if ($user->isSuperAdmin()) {
            if ($filterId && $filterId !== 'all') {
                $query->where('branch_id', $filterId);
            }
        }
        // 2. Admin Logic: Restrict to their own branch
        elseif ($user->isAdmin()) {
            $query->where('branch_id', $user->branch_id);
        }
        // 3. Staff Logic: Restrict to their own records
        elseif ($user->isStaff()) {
            $query->where('user_id', $user->id);
        }
    }
}
