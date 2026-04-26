<?php

namespace App\Http\Requests\PurchaseOrders;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class GetPurchaseOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // add sort_field and sort_direction from purchase order
            'sort_field' => ['nullable', 'string', Rule::in(['received_at', 'due_at'])],
            'sort_direction' => ['nullable', 'string', Rule::in(['asc', 'desc'])],
            'date_field' => ['nullable', 'string', Rule::in(['due_at', 'received_at'])],
            'mode' => ['nullable', 'string', Rule::in(['weekly', 'monthly', 'yearly'])],
            'date' => ['nullable', 'string'],
            'branch_id' => ['nullable', 'integer', 'exists:branches,id'],
        ];
    }
}
