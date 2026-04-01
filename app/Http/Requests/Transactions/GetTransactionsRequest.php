<?php

namespace App\Http\Requests\Transactions;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class GetTransactionsRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return auth()->check();
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'date' => 'nullable|string',
            'mode' => 'nullable|in:daily,weekly,monthly',
            'status' => 'nullable|string',
            'search' => 'nullable|string',
            'branch_id' => 'nullable',
            'customer' => 'nullable|string',
            'payment_type' => 'nullable|string',
        ];
    }
}
