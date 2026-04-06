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
        'production_authorized' => 'boolean', // This ensures 1 becomes true, 0 becomes false
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

    public function transaction()
    {
        return $this->belongsTo(Transaction::class, 'transaction_id');
    }

    public function getStatusLabelAttribute(): string
    {
        return $this->status->label(); // Calls the method we put in the Enum
    }

    public function getStatusColorAttribute(): string
    {
        return $this->status->color();
    }

    /**
     * Logic to determine if we can move to a specific status.
     */
    public function canMoveTo(SublimationStatus $targetStatus): bool
    {
        // 1. Corporate/PO customers are always allowed to proceed
        if ($this->transaction_type === 'purchase_order') {
            return true;
        }

        // 2. If a Manager manually checked "Authorize Production"
        if ($this->production_authorized) {
            return true;
        }

        // 3. If moving into Production (Sizing, Printing, Sewing, etc.)
        if ($targetStatus->isProductionPhase()) {
            // Must have reached 'Downpayment Complete' OR have a payment record
            return $this->status === SublimationStatus::DOWNPAYMENT_COMPLETE;
        }

        return true;
    }
}
