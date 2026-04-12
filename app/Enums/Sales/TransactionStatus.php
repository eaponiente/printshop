<?php

namespace App\Enums\Sales;

enum TransactionStatus: string
{
    case PENDING = 'pending';
    case PAID = 'paid';

    case PARTIAL = 'partial';
}
