<?php

namespace Payroll\Employee\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Payroll\Employee\Enums\EmployeePosition;
use Payroll\Employee\Enums\EmployeeStatus;

class UpdateEmployeeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->employee);
    }

    public function rules(): array
    {
        return [
            'first_name' => ['required', 'string', 'min:2', 'max:100'],
            'last_name' => ['required', 'string', 'min:2', 'max:100'],
            'middle_name' => ['nullable', 'string', 'max:100'],
            'email' => ['nullable', 'email', 'max:255', Rule::unique('employees', 'email')->ignore($this->employee)],
            'phone' => ['nullable', 'string', 'max:20'],
            'address' => ['nullable', 'string', 'max:500'],
            'birth_date' => ['nullable', 'date'],
            'hire_date' => ['required', 'date'],
            'end_date' => ['nullable', 'date', 'after:hire_date'],
            'branch_id' => ['required', 'exists:branches,id'],
            'position' => ['required', Rule::enum(EmployeePosition::class)],
            'status' => ['required', Rule::enum(EmployeeStatus::class)],
            'daily_rate' => ['required', 'numeric', 'min:0'],
            'sss_number' => ['nullable', 'string', 'max:20'],
            'philhealth_number' => ['nullable', 'string', 'max:20'],
            'pagibig_number' => ['nullable', 'string', 'max:20'],
            'tin_number' => ['nullable', 'string', 'max:20'],
            'default_paid_leave_days' => ['nullable', 'numeric', 'min:0'],
            'paid_leave_balance' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
