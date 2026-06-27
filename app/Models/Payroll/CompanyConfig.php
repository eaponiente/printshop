<?php

namespace App\Models\Payroll;

use Illuminate\Database\Eloquent\Model;

class CompanyConfig extends Model
{
    protected $table = 'company_configurations';

    protected $guarded = ['id'];

    public static function getValue(string $key, mixed $default = null): mixed
    {
        $config = static::where('key', $key)->first();

        return $config ? $config->value : $default;
    }
}
