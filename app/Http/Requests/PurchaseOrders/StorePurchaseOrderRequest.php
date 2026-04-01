<?php

namespace App\Http\Requests\PurchaseOrders;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePurchaseOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // Master Record Validation
            'po_number' => ['required', 'string', 'max:255'],
            'branch_id' => ['required', Rule::exists('branches', 'id')],
            'received_at' => ['required', 'date'],
            'due_at' => ['required', 'date'],

            // Detail (Items) Validation
            'details' => ['required', 'array', 'min:1'], // Must have at least one item
            'details.*.item_name' => ['required', 'string', 'max:255'],
            'details.*.quantity' => ['required', 'integer', 'min:1'],
            'details.*.unit_price' => ['required', 'numeric', 'min:1'],
        ];
    }
}
