<?php

namespace App\Enums\Incentives;

enum IncentiveStatus: string
{
    case PENDING = 'pending';
    case PAID = 'paid';
    case CANCELLED = 'cancelled';
}
