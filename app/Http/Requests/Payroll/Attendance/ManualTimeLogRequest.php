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
            // `tomorrow` is midnight tonight, so the whole of today is allowed
            // while anything genuinely in the future is not. Without a bound a
            // mistyped month (09-11 entered as 11-09) silently books a punch
            // months ahead.
            'timestamp' => ['required', 'date', 'before:tomorrow'],
            'note' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return [
            'timestamp.before' => 'A manual log cannot be dated in the future. Check the month and day.',
        ];
    }
}
