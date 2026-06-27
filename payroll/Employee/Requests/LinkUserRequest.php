<?php

namespace Payroll\Employee\Requests;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;

class LinkUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('employee'));
    }

    public function rules(): array
    {
        return [
            'user_id' => [
                'required',
                'exists:users,id',
                $this->branchMatchRule(),
                $this->notAlreadyLinkedRule(),
            ],
        ];
    }

    private function branchMatchRule(): \Closure
    {
        return function (string $attribute, mixed $value, \Closure $fail) {
            $user = User::find($value);
            $employee = $this->route('employee');

            if ($user && $user->branch_id != $employee->branch_id) {
                $fail('User and employee must belong to the same branch.');
            }
        };
    }

    private function notAlreadyLinkedRule(): \Closure
    {
        return function (string $attribute, mixed $value, \Closure $fail) {
            $employee = $this->route('employee');

            if (User::where('employee_id', $employee->id)->exists()) {
                $fail('Employee already linked to a user.');
            }
        };
    }
}
