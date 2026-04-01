<?php

namespace App\Models;

use App\Enums\Sublimations\SublimationStatus;
use Illuminate\Database\Eloquent\Model;

class Sublimation extends Model
{
    public $table = 'sublimations';

    public $guarded = ['id'];

    protected $casts = [
        'status' => SublimationStatus::class,
    ];

    protected $appends = ['status_label', 'status_color'];


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

    // App\Models\Transaction.php

    public function getStatusLabelAttribute(): string
    {
        return $this->status->label(); // Calls the method we put in the Enum
    }

    public function getStatusColorAttribute(): string
    {
        return $this->status->color();
    }
}
