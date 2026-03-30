<?php

namespace App\Http\Requests\Transactions;

use App\Enums\Sales\TransactionStatus;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

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
        // Get the ID from the route (e.g., /transactions/{transaction})
        $transactionId = $this->route('transaction');

        return [
            // Identification
            // We use Rule::unique to ignore the current record's ID during validation
            'invoice_number' => [
                'required',
                'string',
                'max:255',
                Rule::unique('transactions', 'invoice_number')->ignore($transactionId),
            ],

            'customer_id' => 'required|integer|exists:customers,id',
            'particular' => 'required|string|max:255',
            'description' => 'nullable|string',

            // Financials
            'amount_total' => 'required|numeric|min:0|max:99999999.99',
            'amount_paid' => 'nullable|numeric|min:0|lte:amount_total',

            // Metadata & State
            'payment_type' => 'required|string|in:cash,GCash,Card,Bank Transfer',

            // Relationships
            'staff_id' => 'required|exists:users,id',
            'branch_id' => 'required|exists:branches,id',

            // Timestamps
            'transaction_date' => 'required|date',
            'fulfilled_at' => 'nullable|date|after_or_equal:transaction_date',

            // Audit Trail
            // For updates, requiring a change reason is a great practice
            'change_reason' => 'required|string|min:5',
        ];
    }
}
