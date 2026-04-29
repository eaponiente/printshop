<?php

namespace App\Http\Requests\Transactions;

use App\Enums\Sales\TransactionStatus;
use App\Enums\Sales\TransactionTypeOfPaymentEnum;
use Illuminate\Foundation\Http\FormRequest;

class RefundTransactionPaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        $transaction = $this->route('transaction');
        $user = $this->user();

        return $user->isSuperAdmin() || (int) $user->branch_id === (int) $transaction->branch_id;
    }

    public function rules(): array
    {
        return [
            'payment_type' => 'required|string|in:' . implode(',', array_column(TransactionTypeOfPaymentEnum::cases(), 'value')),
        ];
    }

    public function after(): array
    {
        return [
            function ($validator) {
                $transaction = $this->route('transaction');

                if ($transaction->status !== TransactionStatus::PARTIAL->value) {
                    $validator->errors()->add('payment_type', 'Only partial transactions can be refunded.');
                }

                if ((float) $transaction->amount_paid <= 0) {
                    $validator->errors()->add('payment_type', 'There is no collected amount available to refund.');
                }
            },
        ];
    }
}
