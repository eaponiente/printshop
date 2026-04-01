<?php

namespace App\Enums\Shared;

enum TypeOfPaymentEnum: string
{
    case CASH = 'cash';
    case CARD = 'card';
    case CHECK = 'check';
    case BANK_TRANSFER = 'bank_transfer';
    case GCASH = 'gcash';

    /**
     * Get the human-readable label for the UI.
     */
    public function label(): string
    {
        return match ($this) {
            self::CASH => 'Cash',
            self::CARD => 'Card',
            self::CHECK => 'Check',
            self::BANK_TRANSFER => 'Bank Transfer',
            self::GCASH => 'GCash',

        };
    }

    public static function map(): array
    {
        return collect(self::cases())
            ->map(fn ($case) => [
                'key' => $case->value,
                'value' => $case->label(),
            ])
            ->values()
            ->toArray();
    }
}
