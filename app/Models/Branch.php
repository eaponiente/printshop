<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Branch extends Model
{
    public $table = 'branches';

    protected $fillable = [
        'name',
    ];

    public function users()
    {
        return $this->hasMany(User::class, 'branch_id');
    }

    public function scopeAccessibleBy($query, $user)
    {
        if ($user->role !== 'superadmin') {
            return $query->where('id', $user->branch_id);
        }

        return $query;
    }
}
