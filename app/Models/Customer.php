<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Customer extends Model
{
    use HasFactory;

    protected $appends = [
        'full_name'
    ];

    protected $fillable = [
        'first_name',
        'last_name',
        'company',
    ];

    protected function fullName(): Attribute
    {
        return Attribute::make(
            get: fn () => "{$this->first_name} {$this->last_name}"
        );
    }

    public function transactions()
    {
        return $this->hasMany(Transaction::class, 'customer_id');
    }
}
