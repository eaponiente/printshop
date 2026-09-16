<?php

namespace App\Http\Requests\Payroll\Attendance;

use Illuminate\Foundation\Http\FormRequest;

class StoreCorrectionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            // A correction always describes a day that has already happened.
            // Without the bound, a day/month slip in the date picker (09-11
            // entered as 11-09) books the corrected punch months ahead, and
            // approval writes a TimeLog there.
            'date' => ['required', 'date', 'before_or_equal:today'],
            'correction_type' => ['required', 'string', 'in:missed_punch_in,missed_punch_out,time_adjustment,absent_to_present'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.punch_type' => ['required', 'string', 'in:in,out'],
            'items.*.requested_time' => ['required', 'date_format:H:i'],
            'reason' => ['required', 'string', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return [
            'date.before_or_equal' => 'A correction cannot be for a future date. Check the month and day.',
        ];
    }
}
