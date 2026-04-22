<?php

namespace App\Http\Requests\Transactions;

use App\Enums\Shared\TypeOfPaymentEnum;
use Illuminate\Foundation\Http\FormRequest;

class UpdateTransactionPaymentRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $transaction = $this->route('transaction');
        $user = $this->user();

        return $user->isSuperAdmin() || (int) $user->branch_id === (int) $transaction->branch_id;
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        // Retrieve the actual model instance from the route
        $transaction = $this->route('transaction');

        return [
            /**
             * 1. amount_paid:
             * - Must be numeric.
             * - 'min:0' allows for zero-sum corrections if needed (change 0 to 0.01 if 0 is forbidden).
             * - 'lte' (Less Than or Equal) compares the input to the 'amount_total' column in the DB.
             */
            'amount_paid' => [
                'required',
                'numeric',
                'min:1',
                'lte:' . $transaction->balance,
            ],

            /**
             * 2. status:
             * - Must be one of your defined workflow states.
             * - Prevents "garbage" data from entering the ENUM or string column.
             */
            'status' => [
                'required',
                'string',
                'in:pending,partial,paid',
            ],

            /**
             * 3. payment_type (Highly Recommended):
             * - If you are receiving money, you should log HOW it was received.
             */
            'payment_type' => 'required|string|in:' . implode(',', array_column(TypeOfPaymentEnum::cases(), 'value')),
        ];
    }

    /**
     * Custom error messages for better UX
     */
    public function messages(): array
    {
        return [
            'amount_paid.lte' => 'The paid amount cannot exceed the total invoice amount of ' . $this->route('transaction')->amount_total,
            'amount_paid.min' => 'Please enter a valid payment amount.',
        ];
    }
}
