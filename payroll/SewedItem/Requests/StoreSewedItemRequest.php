<?php

namespace Payroll\SewedItem\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreSewedItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'sublimation_id' => ['required', 'integer', 'exists:sublimations,id'],
            'quantity' => ['required', 'integer', 'min:1'],
            'unit_price' => ['required', 'numeric', 'min:0'],
        ];
    }
}
