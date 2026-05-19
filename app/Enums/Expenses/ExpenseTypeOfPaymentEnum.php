<?php

namespace App\Enums\Expenses;

enum ExpenseTypeOfPaymentEnum: string
{
    case CASH = 'cash';
    case CARD = 'card';
    case CHECK = 'check';
    case BANK_TRANSFER = 'bank_transfer';
    case GCASH = 'gcash';
    case CREDIT = 'credit';

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
            self::CREDIT => 'Credit',
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
