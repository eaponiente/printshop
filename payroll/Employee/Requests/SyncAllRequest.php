<?php

namespace Payroll\Employee\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SyncAllRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->isSuperAdmin();
    }

    public function rules(): array
    {
        return [];
    }
}
