<?php

namespace App\Http\Requests\Sales;

use App\Enums\Expenses\ExpenseStatus;
use App\Enums\Expenses\ExpenseTypeOfPaymentEnum;
use App\Models\Expense;
use Illuminate\Foundation\Http\FormRequest;

class UpdateExpenseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        /** @var Expense $expense */
        $expense = $this->route('expense');
        $isEditableCredit = $expense
            && $expense->payment_type === ExpenseTypeOfPaymentEnum::CREDIT->value
            && $expense->status === ExpenseStatus::PENDING->value;

        return [
            'notes' => ['nullable', 'string'],
            'description' => ['required', 'string', 'max:1000'],
            'vendor_name' => ['nullable', 'string', 'max:255'],
            'expense_date' => ['required', 'date'],
            'receipt' => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:2048'],
            'debtor_branch_id' => $isEditableCredit
                ? ['required', 'exists:branches,id', 'different:branch_id']
                : ['nullable'],
        ];
    }
}
