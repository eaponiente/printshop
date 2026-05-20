<?php

namespace Payroll\Employee\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Payroll\Employee\Enums\EmployeePosition;

class RehireEmployeeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->employee);
    }

    public function rules(): array
    {
        return [
            'daily_rate' => ['required', 'numeric', 'min:0'],
            'rehire_date' => ['required', 'date', 'after:'.$this->employee->end_date],
            'position' => ['required', Rule::enum(EmployeePosition::class)],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
