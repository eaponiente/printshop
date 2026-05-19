<?php

namespace App\Http\Requests\Sales;

use App\Enums\Expenses\ExpenseTypeOfPaymentEnum;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreExpenseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'description' => ['required', 'string', 'max:1000'],
            'notes' => ['nullable', 'string'],
            'vendor_name' => ['nullable', 'string', 'max:255'],
            'amount' => ['required', 'numeric', 'min:0'],
            'payment_type' => ['required', Rule::in(ExpenseTypeOfPaymentEnum::cases())],
            'branch_id' => ['required', 'exists:branches,id'],
            'debtor_branch_id' => [
                Rule::requiredIf(fn () => $this->input('payment_type') === ExpenseTypeOfPaymentEnum::CREDIT->value),
                'nullable',
                'exists:branches,id',
                'different:branch_id',
            ],
            'expense_date' => ['required', 'date'],
            'receipt' => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:2048'],
        ];
    }
}
