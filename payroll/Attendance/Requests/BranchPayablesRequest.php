<?php

namespace Payroll\Attendance\Requests;

use Illuminate\Foundation\Http\FormRequest;

class BranchPayablesRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = auth()->user();

        return $user->isAdmin() || $user->isSuperAdmin();
    }

    public function rules(): array
    {
        return [
            'period_ids'   => ['nullable', 'array'],
            'period_ids.*' => ['integer', 'exists:payroll_periods,id'],
        ];
    }
}
