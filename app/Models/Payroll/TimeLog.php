<?php

namespace App\Models\Payroll;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Payroll\Attendance\Enums\PunchSource;
use Payroll\Attendance\Enums\PunchType;

class TimeLog extends Model
{
    const UPDATED_AT = null;

    protected $guarded = ['id'];

    public $timestamps = ['created_at'];

    protected function casts(): array
    {
        return [
            'type' => PunchType::class,
            'source' => PunchSource::class,
            'timestamp' => 'datetime',
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function duplicate(): BelongsTo
    {
        return $this->belongsTo(TimeLog::class, 'duplicate_of');
    }
}
