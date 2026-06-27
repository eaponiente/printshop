<?php

namespace App\Models\Payroll;

use Illuminate\Database\Eloquent\Model;

class PayrollSetting extends Model
{
    protected $table = 'payroll_settings';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'value' => 'string',
        ];
    }
}
