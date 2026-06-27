<?php

namespace App\Models\Payroll;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CorrectionRequestItem extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'requested_time' => 'datetime',
        ];
    }

    public function correctionRequest(): BelongsTo
    {
        return $this->belongsTo(CorrectionRequest::class);
    }
}
