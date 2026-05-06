<?php

namespace App\Enums\Expenses;

enum ExpenseStatus: string
{
    case PENDING = 'pending';
    case PAID = 'paid';
    case VOID = 'void';
    case REJECTED = 'rejected';
}
