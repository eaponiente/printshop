<?php

namespace App\Models;

use App\Enums\Users\UserRole;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Branch extends Model
{
    use HasFactory;

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
        if ($user->role === UserRole::STAFF->value) {
            return $query->where('id', $user->branch_id);
        }

        return $query;
    }
}
