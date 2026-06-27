<?php

namespace Payroll\Attendance\Requests;

use App\Models\Payroll\Employee;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class StoreFineRequest extends FormRequest
{
    public function authorize(): bool
    {
        $employee = Employee::findOrFail($this->input('employee_id'));

        return Gate::allows('fines.mark', [$employee->branch_id]);
    }

    public function rules(): array
    {
        return [
            'employee_id' => ['required', 'exists:employees,id'],
            'date' => ['required', 'date'],
            'fine_type' => ['required', 'string', 'max:50'],
            'amount' => ['required', 'numeric', 'min:0'],
            'note' => ['nullable', 'string', 'max:500'],
        ];
    }
}
