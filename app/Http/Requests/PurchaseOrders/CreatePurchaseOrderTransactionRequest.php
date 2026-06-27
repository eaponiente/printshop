<?php

namespace App\Http\Requests\PurchaseOrders;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class CreatePurchaseOrderTransactionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $purchaseOrder = $this->route('purchaseOrder');
        $maxAmount = $purchaseOrder->grand_total;

        return [
            'amount_total' => "required|numeric|min:0|max:{$maxAmount}",
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function ($validator) {
            $purchaseOrder = $this->route('purchaseOrder');

            // Check if the purchase order exists and doesn't have an assigned user
            if (! $purchaseOrder || empty($purchaseOrder->assigned_user_id)) {
                $validator->errors()->add(
                    'amount_total',
                    'A user must be assigned to the purchase order before creating a transaction.'
                );
            }
        });
    }
}
