<?php

namespace App\Http\Requests\Settings;

use Illuminate\Foundation\Http\FormRequest;

class StoreSublimationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'description' => ['required', 'string'],
            'notes' => ['nullable', 'string'],
            'branch_id' => ['required', 'exists:branches,id'],
            'quantity' => ['required', 'integer', 'min:1', 'max:999999'],
            'customer_id' => ['required', 'exists:customers,id'],
            // Financials (numeric handles decimal input; gte:0 prevents negative numbers)
            'amount_total' => 'required|numeric|min:1|max:99999999.99',
            'tag_ids' => ['required', 'array'],
            'tag_ids.*' => ['exists:tags,id'],
            'user_id' => ['nullable', 'exists:users,id'],
        ];
    }
}
