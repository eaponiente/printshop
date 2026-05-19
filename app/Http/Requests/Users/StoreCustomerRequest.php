<?php

namespace App\Http\Requests\Users;

use Illuminate\Foundation\Http\FormRequest;

class StoreCustomerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Authentication handled in route middleware
    }

    public function rules(): array
    {
        return [
            'first_name' => [
                'required_without:company',
                'nullable',
                'string',
                'min:2',
                'max:255',
            ],
            'last_name' => [
                'required_with:first_name',
                'nullable',
                'string',
                'min:2',
                'max:255',
            ],
            'company' => [
                'required_without:first_name',
                'nullable',
                'string',
                'min:2',
                'max:255',
            ],
        ];
    }
}
