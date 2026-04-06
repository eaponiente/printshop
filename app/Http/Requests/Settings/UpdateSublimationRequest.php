<?php

namespace App\Http\Requests\Settings;

use App\Enums\Sublimations\SublimationStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateSublimationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->user());
    }

    public function rules(): array
    {
        return [
            'description' => ['required', 'string'],
            'branch_id' => ['required', 'exists:branches,id'],
            'customer_id' => ['required', 'exists:customers,id'],
            'user_id' => ['required', 'exists:users,id'],
            'status' => ['required', Rule::in(SublimationStatus::cases())],
            'due_at' => ['required', 'date'],
            'amount_total' => 'required|numeric|min:0|max:99999999.99',
            'transaction_type' => 'required|in:retail,purchase_order',
            'production_authorized' => 'required|boolean',
            // amount_paid must not be greater than amount_total
        ];
    }
}
