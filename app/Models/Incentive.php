<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Incentive extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'incentives';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'month' => 'integer',
            'year' => 'integer',
            'net_income' => 'decimal:2',
            'incentive_amount' => 'decimal:2',
            'owner_contribution' => 'decimal:2',
            'paid_at' => 'datetime',
        ];
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
