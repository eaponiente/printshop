<?php

namespace App\Http\Requests\PurchaseOrders;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

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
}
