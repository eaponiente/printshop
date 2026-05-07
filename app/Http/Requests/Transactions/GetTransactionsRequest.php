<?php

namespace App\Http\Requests\Transactions;

use App\Enums\Sales\TransactionTypeOfPaymentEnum;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class GetTransactionsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check();
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'date' => 'nullable|string',
            'mode' => 'nullable|in:daily,weekly,monthly,yearly',
            'tab' => 'nullable|in:payments,unpaid',
            'status' => 'nullable|string',
            'search' => 'nullable|string',
            'branch_id' => 'nullable',
            'payment_type' => ['nullable', 'string', 'in:' . implode(',', array_merge(array_map(fn ($c) => $c->value, TransactionTypeOfPaymentEnum::cases()), ['all']))],
            'customer' => 'nullable|string',
            'sort_field' => 'nullable|string|in:transaction_date,created_at',
            'sort_direction' => 'nullable|string|in:asc,desc',
        ];
    }
}
