<?php

namespace App\Http\Requests\Payroll\Settings;

use App\Models\Payroll\PayrollSetting;
use Illuminate\Foundation\Http\FormRequest;

class UpdatePayrollSettingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', PayrollSetting::class);
    }

    public function rules(): array
    {
        return [
            'late_deduction_per_minute' => ['required', 'numeric', 'min:0'],
            'late_deduction_threshold_minutes' => ['required', 'integer', 'min:1'],
            'half_day_threshold_minutes' => ['required', 'integer', 'min:1'],
            'no_break_fine' => ['required', 'numeric', 'min:0'],
        ];
    }
}
