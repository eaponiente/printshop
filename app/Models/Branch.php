<?php

namespace App\Models;

use App\Enums\Users\UserRole;
use App\Models\Payroll\Holiday;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Branch extends Model
{
    use HasFactory;

    public $table = 'branches';

    protected $fillable = [
        'name',
        'latitude',
        'longitude',
        'geofence_radius',
    ];

    public function users()
    {
        return $this->hasMany(User::class, 'branch_id');
    }

    public function holidays(): BelongsToMany
    {
        return $this->belongsToMany(Holiday::class, 'branch_holiday')->withTimestamps();
    }

    public function scopeAccessibleBy($query, $user)
    {
        if (
            $user->role === UserRole::ADMIN->value ||
            $user->role === UserRole::STAFF->value
        ) {
            return $query->where('id', $user->branch_id);
        }

        return $query;
    }
}
