<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PurchaseOrderDetail extends Model
{
    use HasFactory;
    protected $fillable = [
        'purchase_order_id',
        'item_name',
        'quantity',
        'unit_price',
        'item_description',
    ];
}
