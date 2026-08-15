<?php

namespace Payroll\Attendance\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;
use Payroll\Attendance\Enums\HolidayType;

class UpdateHolidayRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('holiday'));
    }

    /**
     * Normalize branch_ids to a clean list of ints. Without this a numeric
     * string ("3") survives into HolidayService, where the old/new scope
     * comparison is a strict === against ints from the database — it would
     * miss, and the sheet reprocess would run twice for no reason.
     */
    protected function prepareForValidation(): void
    {
        if (! $this->has('branch_ids')) {
            return;
        }

        $this->merge([
            'branch_ids' => array_values(array_unique(array_map(
                'intval',
                array_filter(
                    (array) $this->input('branch_ids'),
                    fn ($value) => $value !== '' && $value !== null,
                ),
            ))),
        ]);
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:200'],
            'date' => ['required', 'date'],
            'type' => ['required', Rule::enum(HolidayType::class)],
            'recurring' => ['boolean'],
            'branch_ids' => ['array'],
            'branch_ids.*' => ['integer', 'exists:branches,id'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if (
                $this->input('type') === HolidayType::REGULAR->value
                // NOT filled(): Laravel's isEmptyString() short-circuits on
                // is_array(), so filled() is true for an empty array too, and
                // a regular holiday submitted with branch_ids: [] — which is
                // valid, and exactly what the form sends — would be rejected.
                && $this->input('branch_ids', []) !== []
            ) {
                $validator->errors()->add(
                    'branch_ids',
                    'Regular holidays always apply to every branch.'
                );
            }
        });
    }
}
