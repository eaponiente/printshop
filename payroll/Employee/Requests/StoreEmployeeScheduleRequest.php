<?php

namespace Payroll\Employee\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreEmployeeScheduleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->employee);
    }

    public function rules(): array
    {
        return [
            'start_time' => ['required', 'date_format:H:i'],
            'end_time' => ['required', 'date_format:H:i', 'after:start_time'],
            'unpaid_tail_minutes' => ['nullable', 'integer', 'min:0', 'max:120'],
            'rest_days' => ['required', 'array', 'min:1'],
            'rest_days.*' => ['integer', 'min:0', 'max:6'],
            'effective_from' => ['required', 'date'],
            'effective_to' => ['nullable', 'date', 'after_or_equal:effective_from'],
            'is_active' => ['boolean'],
        ];
    }
}
