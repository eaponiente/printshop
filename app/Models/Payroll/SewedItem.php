<?php

namespace App\Models\Payroll;

use App\Models\Branch;
use App\Models\Sublimation;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SewedItem extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'unit_price' => 'decimal:2',
            'amount' => 'decimal:2',
            'sewed_date' => 'date:Y-m-d',
        ];
    }

    public function sublimation(): BelongsTo
    {
        return $this->belongsTo(Sublimation::class);
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
