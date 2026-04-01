<?php

namespace App\Enums\Sales;

enum TransactionStatus
{
    const PENDING = 'pending';
    const PAID = 'paid';

    const PARTIAL = 'partial';
}
