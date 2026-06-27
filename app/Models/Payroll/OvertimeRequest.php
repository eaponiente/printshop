<?php

namespace App\Models\Payroll;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OvertimeRequest extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'date' => 'date:Y-m-d',
            'start_time' => 'datetime',
            'end_time' => 'datetime',
            'approved_at' => 'datetime',
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function getApprovedMinutes(): int
    {
        if (! $this->start_time || ! $this->end_time) {
            return 0;
        }

        return max(0, Carbon::parse($this->start_time)->diffInMinutes(Carbon::parse($this->end_time)));
    }
}
