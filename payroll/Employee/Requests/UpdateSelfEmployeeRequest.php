<?php

namespace Payroll\Employee\Requests;

use App\Models\Payroll\Employee;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class UpdateSelfEmployeeRequest extends FormRequest
{
    public function authorize(): bool
    {
        $employeeId = Auth::user()->employee_id;

        return $employeeId && Auth::user()->can('editOwn', $this->employee());
    }

    public function rules(): array
    {
        $employeeId = Auth::user()->employee_id;

        return [
            'first_name' => ['required', 'string', 'min:2', 'max:100'],
            'last_name' => ['required', 'string', 'min:2', 'max:100'],
            'middle_name' => ['nullable', 'string', 'max:100'],
            'email' => ['nullable', 'email', 'max:255', Rule::unique('employees', 'email')->ignore($employeeId)],
            'phone' => ['nullable', 'string', 'max:20'],
            'address' => ['nullable', 'string', 'max:500'],
            'birth_date' => ['nullable', 'date'],
            'sss_number' => ['nullable', 'string', 'max:20'],
            'philhealth_number' => ['nullable', 'string', 'max:20'],
            'pagibig_number' => ['nullable', 'string', 'max:20'],
            'tin_number' => ['nullable', 'string', 'max:20'],
        ];
    }

    private function employee()
    {
        return Employee::find(Auth::user()->employee_id);
    }
}
