<?php

namespace App\Http\Requests\Settings;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateTagRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->user());
    }

    public function rules(): array
    {
        $tagId = $this->route('tag')?->id;

        return [
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('tags', 'name')->ignore($tagId),
            ],
            'color' => [
                'required',
                'string',
                'max:7',
            ],
            'price_per_piece' => [
                'nullable',
                'numeric',
                'min:0',
                'max:999999.99',
            ],
        ];
    }
}
