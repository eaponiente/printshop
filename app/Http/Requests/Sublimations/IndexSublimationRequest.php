<?php

namespace App\Http\Requests\Sublimations;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class IndexSublimationRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'branch_id' => 'nullable|exists:branches,id',
            'status' => 'nullable',
            'include_completed' => 'nullable',
            'user_id' => 'nullable|exists:users,id',
            'sort_field' => 'nullable|string|in:due_at,user_id',
            'sort_direction' => 'nullable|string|in:asc,desc',
        ];
    }
}
