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
            'tags' => ['required', 'array', 'min:1'],
            'tags.*.tag_id' => ['required', 'integer', 'exists:tags,id'],
            'tags.*.quantity' => ['required', 'integer', 'min:1'],
            'tags.*.price_per_piece' => ['required', 'numeric', 'min:0'],
        ];
    }
}
