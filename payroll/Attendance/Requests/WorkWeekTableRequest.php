<?php

namespace Payroll\Attendance\Requests;

use Illuminate\Foundation\Http\FormRequest;

class WorkWeekTableRequest extends FormRequest
{
    public function authorize(): bool
    {
        return ! auth()->user()->isStaff();
    }

    public function rules(): array
    {
        return [
            'branch_id' => ['nullable', 'integer', 'exists:branches,id'],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
        ];
    }
}
