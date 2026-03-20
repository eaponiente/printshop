<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Transaction extends Model
{
    public $guarded = ['id'];

    protected $casts = [
        'transaction_date' => 'datetime:Y-m-d h:i A',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'staff_id');
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class, 'branch_id');
    }
}
