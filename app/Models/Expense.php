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
        });;
    }
}
