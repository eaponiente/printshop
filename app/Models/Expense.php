<?php

namespace App\Models;

use App\Concerns\SaleFilterTrait;
use Illuminate\Database\Eloquent\Model;

class Expense extends Model
{
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
}
