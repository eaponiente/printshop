<?php

namespace App\Http\Requests\Payroll\Attendance;

use Illuminate\Foundation\Http\FormRequest;

class PunchLocationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            'accuracy_meters' => ['nullable', 'integer', 'min:0', 'max:1000000'],
        ];
    }
}
