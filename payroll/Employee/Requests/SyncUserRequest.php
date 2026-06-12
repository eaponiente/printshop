<?php

namespace Payroll\Employee\Requests;

use App\Models\Payroll\Employee;
use Illuminate\Foundation\Http\FormRequest;

class SyncUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', Employee::class);
    }

    public function rules(): array
    {
        return [];
    }

    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            if ($this->route('user')->employee_id !== null) {
                $validator->errors()->add('user_id', 'User already linked to an employee.');
            }
        });
    }
}
