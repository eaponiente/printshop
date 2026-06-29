<?php

namespace App\Http\Requests\Settings;

use Illuminate\Foundation\Http\FormRequest;

class UpdateSublimationRequest extends FormRequest
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
            'customer_id' => ['required', 'exists:customers,id'],
            'amount_total' => 'required|numeric|min:0|max:99999999.99',
            'transaction_type' => 'required|in:retail,purchase_order',
            'production_authorized' => 'required|boolean',
            'tag_ids' => ['required', 'array', 'min:1'],
            'tag_ids.*.id' => ['required', 'integer', 'exists:tags,id'],
            'tag_ids.*.quantity' => ['required', 'integer', 'min:1'],
            'user_id' => ['nullable', 'exists:users,id'],
        ];
    }
}
