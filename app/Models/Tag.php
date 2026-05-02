<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Tag extends Model
{
    public $fillable = ['name', 'color'];

    public function sublimations()
    {
        return $this->belongsToMany(Sublimation::class, 'sublimation_tag');
    }
}
