<?php

namespace App\Http\Requests\Transactions;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreTransactionRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            // Identification
            'customer_id' => 'required|integer|exists:customers,id',
            'particular' => 'required|string|max:255',
            'description' => 'nullable|string',

            // Financials (numeric handles decimal input; gte:0 prevents negative numbers)
            'amount_total' => 'required|numeric|min:0|max:99999999.99',
            'amount_paid' => 'nullable|numeric|min:0|lte:amount_total',

            // Metadata & State
            'payment_type' => 'required|string|in:cash,gcash,card,bank_transfer', // Add your specific types
            'status' => 'required|string|in:pending,partial,paid,void',

            // Relationships (ensures IDs actually exist in their respective tables)
            'branch_id' => 'required|exists:branches,id',

            // Timestamps
            'fulfilled_at' => 'nullable|date|after_or_equal:transaction_date',

            // Audit Trail
            'change_reason' => 'nullable|string|required_if:status,void', // Example logic
        ];
    }
}
