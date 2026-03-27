<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Endorsement extends Model
{
    protected $table = 'endorsements';

    protected $fillable = [
        'branch_id',
        'amount',
        'user_id',
    ];

    protected $casts = [
        'created_at' => 'datetime:M d Y',
    ];

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
