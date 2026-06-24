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
            'tags' => ['required', 'array', 'min:1'],
            'tags.*.tag_id' => ['required', 'integer', 'exists:tags,id'],
            'tags.*.quantity' => ['required', 'integer', 'min:1'],
            'tags.*.price_per_piece' => ['required', 'numeric', 'min:0'],
            'notes' => ['required', 'string', 'max:1000'],
        ];
    }
}
