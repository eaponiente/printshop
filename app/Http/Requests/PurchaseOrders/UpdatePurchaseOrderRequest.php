<?php

namespace App\Http\Requests\PurchaseOrders;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePurchaseOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->user());
    }

    public function rules(): array
    {
        return [
            'status' => ['required'],
            'po_number' => ['sometimes', 'string'],
            'details' => ['sometimes', 'array'],
            'details.*.quantity' => ['required_with:details', 'integer', 'gte:1'],
            'details.*.unit_price' => ['required_with:details', 'numeric', 'gte:1'],
        ];
    }
}
