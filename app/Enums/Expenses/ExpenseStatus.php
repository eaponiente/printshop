<?php

namespace App\Enums\Expenses;

enum ExpenseStatus: string
{
    case PAID = 'paid';

    case VOID = 'void';
}
