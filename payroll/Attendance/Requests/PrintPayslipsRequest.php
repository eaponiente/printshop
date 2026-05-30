<?php

namespace Payroll\Attendance\Requests;

use App\Models\Branch;
use Illuminate\Foundation\Http\FormRequest;

class PrintPayslipsRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = auth()->user();

        if ($user->isStaff()) {
            return false;
        }

        return true;
    }

    public function rules(): array
    {
        return [
            'branch_id' => ['required', 'exists:branches,id'],
            'period_id' => ['required', 'exists:payroll_periods,id'],
        ];
    }

    public function validatedBranch(): Branch
    {
        return Branch::findOrFail($this->validated('branch_id'));
    }
}
