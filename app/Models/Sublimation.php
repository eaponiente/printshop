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
        if (auth()->user()->role === 'superadmin') {
            return true;
        }

        if ($this->status === SublimationStatus::COMPLETED) {
            return false;
        }

        if ($this->transaction_type === 'purchase_order' || $this->production_authorized) {
            return true;
        }

        // 1. Phase 3 Logic (Claimed -> Completed)
        if ($this->status === SublimationStatus::CLAIMED) {
            return $targetStatus === SublimationStatus::COMPLETED;
        }

        if ($targetStatus === SublimationStatus::COMPLETED) {
            return $this->status === SublimationStatus::CLAIMED;
        }

        // 2. The Bridge: Moving from Phase 1 to DOWNPAYMENT_COMPLETE
        // This allows the jump from setup to the first production step.
        if ($targetStatus === SublimationStatus::DOWNPAYMENT_COMPLETE) {
            return $this->status === SublimationStatus::WAITING_FOR_DP;
        }

        // 3. Phase 2 Logic (Inside Production)
        if ($targetStatus->isProductionPhase()) {
            // Once here, you can only stay here if you are already in production
            // (and you already handled the DP_COMPLETE entry above)
            return $this->status->isProductionPhase();
        }

        // 4. Phase 1 Logic (Pre-Payment)
        if ($targetStatus->isPrePaymentPhase()) {
            // Allow movement within setup, but block moving BACK from production
            return $this->status->isPrePaymentPhase();
        }

        return false; // Default to block unknown transitions
    }
}
