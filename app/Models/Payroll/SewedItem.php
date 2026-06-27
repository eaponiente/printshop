<?php

namespace App\Models\Payroll;

use App\Models\Branch;
use App\Models\Sublimation;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

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
            'completed_at' => 'datetime',
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

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class, 'sewed_item_tag')
            ->withPivot('quantity', 'price_per_piece')
            ->withTimestamps();
    }
}
