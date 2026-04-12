<?php

namespace App\Http\Requests\Transactions;

use Illuminate\Foundation\Http\FormRequest;

class UpdateTransactionRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        // Usually true, or check if user owns the transaction
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'customer_id' => 'required|integer|exists:customers,id',
            'particular' => 'required|string|max:255',
            'description' => 'nullable|string',

            // Financials
            'amount_total' => 'required|numeric|min:0|max:99999999.99',

            // Relationships
            'branch_id' => 'required|exists:branches,id',

        ];
    }
}
