<?php

namespace App\Http\Requests\Sales;

use App\Enums\Expenses\ExpenseTypeOfPaymentEnum;
use App\Enums\Sales\TransactionTypeOfPaymentEnum;
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
            'vendor_name' => ['nullable', 'string', 'max:255'],
            'amount' => ['required', 'numeric', 'min:0'],
            'payment_type' => ['required', Rule::in(ExpenseTypeOfPaymentEnum::cases())],
            'branch_id' => ['required', 'exists:branches,id'],
            'expense_date' => ['required', 'date'],
            'receipt' => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:2048'],
        ];
    }
}
