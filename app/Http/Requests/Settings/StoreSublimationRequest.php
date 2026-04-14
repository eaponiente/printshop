<?php

namespace App\Http\Requests\Settings;

use App\Enums\Sublimations\SublimationStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

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
            'branch_id' => ['required', 'exists:branches,id'],
            'customer_id' => ['required', 'exists:customers,id'],
            'user_id' => ['required', 'exists:users,id'],
            'due_at' => ['required', 'date', 'after:today'],
            // Financials (numeric handles decimal input; gte:0 prevents negative numbers)
            'amount_total' => 'required|numeric|min:0|max:99999999.99',
            'notes' => 'nullable|string',
        ];
    }
}
