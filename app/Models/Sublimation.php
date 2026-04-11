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
        // if auth user role == superadmin return true
        if (auth()->user()->role === 'superadmin') {
            return true;
        }

        // 1. Terminal State: Once "Completed", no further changes allowed
        if ($this->status === SublimationStatus::COMPLETED) {
            return false;
        }

        // 2. Global Bypasses
        // Corporate/PO customers or manual Manager authorization bypasses phase gates
        if ($this->transaction_type === 'purchase_order' || $this->production_authorized) {
            return true;
        }

        // 3. Phase 3 Logic (Claimed -> Completed)
        // If current status is CLAIMED, the ONLY valid target is COMPLETED
        if ($this->status === SublimationStatus::CLAIMED) {
            return $targetStatus === SublimationStatus::COMPLETED;
        }

        // Conversely, you cannot move to COMPLETED unless you are currently CLAIMED
        if ($targetStatus === SublimationStatus::COMPLETED) {
            return $this->status === SublimationStatus::CLAIMED;
        }

        // 4. Phase 2 Logic (Moving into or within Production)
        // If target is Sizing, Printing, Sewing, etc.
        if ($targetStatus->isProductionPhase()) {
            // Must have reached 'Downpayment Complete' to enter or stay in Phase 2
            return $this->status === SublimationStatus::DOWNPAYMENT_COMPLETE || $this->status->isProductionPhase();
        }

        // 5. Phase 1 Logic (Pre-Payment)
        // If moving within setup phases (Approval, Layout, Waiting for DP)
        if ($targetStatus->isPrePaymentPhase()) {
            // We generally allow free movement within Phase 1 for flexibility,
            // but we prevent moving BACK to Phase 1 once Production (Phase 2) has started.
            return $this->status->isPrePaymentPhase();
        }

        return true;
    }
}
