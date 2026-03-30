<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Sublimation extends Model
{
    public $table = 'sublimations';

    public $guarded = ['id'];

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function tags()
    {
        return $this->belongsToMany(Tag::class, 'sublimation_tag', 'sublimation_id', 'tag_id');
    }
}
