<?php

namespace App\Enums\Sublimations;

enum SublimationStatus: string
{
    case FOR_APPROVAL = 'for approval';
    case DONE_LAYOUT = 'done layout';
    case WAITING_FOR_DP = 'waiting for dp';
    case DOWNPAYMENT_COMPLETE = 'downpayment complete';
    case FOR_SIZING = 'for sizing';
    case DONE_SIZING = 'done sizing';
    case PRINTED = 'printed';
    case CUT = 'cut';
    case PRINTED_RED = 'printed_alt'; // Handling the duplicate "Printed" in red
    case SEWING = 'sewing';
    case SEWED = 'sewed';
    case CHECKED = 'checked';
    case READY_FOR_PICKUP = 'ready for pickup';
    case CLAIMED = 'claimed';
    case COMPLETED = 'completed';

    /**
     * Get the human-readable label for the UI.
     */
    public function label(): string
    {
        return match ($this) {
            self::FOR_APPROVAL => 'For Approval',
            self::DONE_LAYOUT => 'Done Layout',
            self::WAITING_FOR_DP => 'Waiting for DP',
            self::DOWNPAYMENT_COMPLETE => 'Downpayment Complete',
            self::FOR_SIZING => 'For Sizing',
            self::DONE_SIZING => 'Done Sizing',
            self::PRINTED => 'Printed',
            self::CUT => 'Cut',
            self::PRINTED_RED => 'Printed',
            self::SEWING => 'Sewing',
            self::SEWED => 'Sewed',
            self::CHECKED => 'Checked',
            self::READY_FOR_PICKUP => 'Ready for Pickup',
            self::CLAIMED => 'Claimed',
            self::COMPLETED => 'Completed',
        };
    }

    /**
     * Returns Tailwind CSS classes based on your screenshot's colors.
     */
    public function color(): string
    {
        return match ($this) {
            // Phase 1: Planning & Payment
            self::FOR_APPROVAL => 'bg-blue-500 text-white',
            self::DONE_LAYOUT => 'bg-indigo-100 text-indigo-700', // Changed from green to Indigo for distinction
            self::WAITING_FOR_DP => 'bg-slate-400 text-white',
            self::DOWNPAYMENT_COMPLETE => 'bg-emerald-500 text-white', // Solid milestone green

            // Phase 2: Production (Varied colors to spot bottlenecks)
            self::FOR_SIZING => 'bg-pink-300 text-black',
            self::DONE_SIZING => 'bg-cyan-400 text-black',
            self::PRINTED => 'bg-teal-200 text-black',
            self::CUT => 'bg-purple-300 text-black',
            self::PRINTED_RED => 'bg-red-500 text-white',
            self::SEWING => 'bg-violet-300 text-black',
            self::SEWED => 'bg-orange-400 text-white',
            self::CHECKED => 'bg-rose-400 text-white',

            // Phase 3: Delivery & Finalization
            self::READY_FOR_PICKUP => 'bg-amber-500 text-white',
            self::CLAIMED => 'bg-yellow-400 text-black',
            self::COMPLETED => 'bg-green-700 text-white', // Darker, "Finished" Green
        };
    }

    public static function map(): array
    {
        return collect(self::cases())
            ->map(fn ($case) => [
                'key' => $case->value,
                'value' => $case->label(),
                'color' => $case->color(),
            ])
            ->values()
            ->toArray();
    }

    public function isPrePaymentPhase(): bool
    {
        return in_array($this, [
            self::FOR_APPROVAL,
            self::DONE_LAYOUT,
            self::WAITING_FOR_DP,
            self::DOWNPAYMENT_COMPLETE,
        ]);
    }

    public function isProductionPhase(): bool
    {
        return in_array($this, [
            self::FOR_SIZING,
            self::DONE_SIZING,
            self::PRINTED,
            self::CUT,
            self::PRINTED_RED,
            self::SEWING,
            self::SEWED,
            self::CHECKED,
            self::READY_FOR_PICKUP,
            self::CLAIMED, // Claimed is the transition out of production
        ]);
    }
}
