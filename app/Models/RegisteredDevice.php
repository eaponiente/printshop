<?php

namespace App\Models;

use Database\Factories\RegisteredDeviceFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RegisteredDevice extends Model
{
    /** @use HasFactory<RegisteredDeviceFactory> */
    use HasFactory;

    protected $fillable = [
        'user_id',
        'device_token',
        'device_name',
        'branch_id',
        'registered_by',
        'last_used_at',
        'is_active',
        'is_approved',
        'approved_by',
        'approved_at',
    ];

    protected function casts(): array
    {
        return [
            'last_used_at' => 'datetime',
            'approved_at' => 'datetime',
            'is_active' => 'boolean',
            'is_approved' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function registeredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'registered_by');
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function scopeApproved($query)
    {
        return $query->where('is_approved', true)->where('is_active', true);
    }

    public function scopePending($query)
    {
        return $query->where('is_approved', false)->where('is_active', true);
    }
}
