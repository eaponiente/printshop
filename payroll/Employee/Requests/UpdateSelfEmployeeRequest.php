<?php

namespace Payroll\Employee\Requests;

use App\Models\Payroll\Employee;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

class UpdateSelfEmployeeRequest extends FormRequest
{
    public function authorize(): bool
    {
        $employeeId = Auth::user()->employee_id;

        return $employeeId && Auth::user()->can('editOwn', $this->employee());
    }

    public function rules(): array
    {
        return [
            'phone' => ['nullable', 'string', 'max:20'],
            'address' => ['nullable', 'string', 'max:500'],
            'birth_date' => ['nullable', 'date'],
            'sss_number' => ['nullable', 'string', 'max:20'],
            'philhealth_number' => ['nullable', 'string', 'max:20'],
            'pagibig_number' => ['nullable', 'string', 'max:20'],
        ];
    }

    private function employee()
    {
        return Employee::find(Auth::user()->employee_id);
    }
}
