<?php

namespace App\Http\Requests\Payroll\Attendance;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Payroll\Attendance\Enums\PunchType;

class PunchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'type' => ['required', 'string', Rule::enum(PunchType::class)],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'accuracy_meters' => ['nullable', 'integer', 'min:0', 'max:1000000'],
            'timestamp' => ['nullable', 'date_format:Y-m-d H:i:s'],
        ];
    }

    public function punchType(): PunchType
    {
        return PunchType::from($this->input('type'));
    }
}
