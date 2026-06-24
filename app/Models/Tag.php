<?php

namespace App\Models;

use App\Models\Payroll\SewedItem;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Tag extends Model
{
    public $fillable = ['name', 'color', 'price_per_piece'];

    protected function casts(): array
    {
        return [
            'price_per_piece' => 'decimal:2',
        ];
    }

    public function sewedItems(): BelongsToMany
    {
        return $this->belongsToMany(SewedItem::class, 'sewed_item_tag')
            ->withPivot('quantity', 'price_per_piece')
            ->withTimestamps();
    }
}
