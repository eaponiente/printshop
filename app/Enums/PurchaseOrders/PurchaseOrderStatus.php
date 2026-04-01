<?php

namespace App\Enums\PurchaseOrders;

enum PurchaseOrderStatus: string
{
    case PENDING = 'pending';
    case ACTIVE = 'active';
    case FINISHED = 'finished';
    case RELEASED = 'released';

    /**
     * Get the human-readable label for the UI.
     */
    public function label(): string
    {
        return match ($this) {
            self::PENDING => 'Pending',
            self::ACTIVE => 'Active',
            self::FINISHED => 'Finished',
            self::RELEASED => 'Released',
        };
    }

    /**
     * Returns Tailwind CSS classes based on the status.
     * Feel free to adjust the colors according to your design system.
     */
    public function color(): string
    {
        return match ($this) {
            self::PENDING => 'bg-gray-400 text-white',
            self::ACTIVE => 'bg-blue-500 text-white',
            self::FINISHED => 'bg-green-500 text-white',
            self::RELEASED => 'bg-purple-500 text-white',
        };
    }

    /**
     * Returns the cases formatted for Inertia/React select options.
     */
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
}
