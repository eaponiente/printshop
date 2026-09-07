<?php

namespace App\Http\Requests\Payroll\Attendance;

use Illuminate\Foundation\Http\FormRequest;

class ManualTimeLogRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'employee_id' => ['required', 'exists:employees,id'],
            'type' => ['required', 'string', 'in:in,lunch_out,lunch_in,out,overtime_in,overtime_out'],
            'timestamp' => ['required', 'date'],
            'note' => ['nullable', 'string', 'max:500'],
        ];
    }
}
