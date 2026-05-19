<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CashOnHand extends Model
{
    use HasFactory;

    public $table = 'cash_on_hands';

    public $guarded = ['id'];
}
