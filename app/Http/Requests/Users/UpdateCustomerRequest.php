<?php

namespace App\Http\Requests\Users;

use Illuminate\Foundation\Http\FormRequest;

class UpdateCustomerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->user());
    }

    public function rules(): array
    {
        return [
            'first_name' => [
                'required_without:company',
                'nullable',
                'string',
                'min:2',
                'max:255'
            ],
            'last_name' => [
                'required_with:first_name',
                'nullable',
                'string',
                'min:2',
                'max:255'
            ],
            'company' => [
                'required_without:first_name',
                'nullable',
                'string',
                'min:2',
                'max:255'
            ],
        ];
    }
}
