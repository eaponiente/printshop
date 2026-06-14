<?php

namespace Payroll\SewedItem\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateSewedItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'quantity' => ['required', 'integer', 'min:1'],
            'unit_price' => ['required', 'numeric', 'min:0'],
            'notes' => ['required', 'string', 'max:1000'],
        ];
    }
}
