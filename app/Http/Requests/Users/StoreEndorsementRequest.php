<?php

namespace App\Http\Requests\Users;

use App\Models\CashOnHand;
use Illuminate\Foundation\Http\FormRequest;

class StoreEndorsementRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $branchId = $this->input('branch_id');

        // Only query if we actually have a branch ID to look up
        $cashOnHand = $branchId
            ? CashOnHand::where('branch_id', $branchId)->first()
            : null;

        $maxAmount = $cashOnHand ? $cashOnHand->amount : 0;

        return [
            'branch_id' => ['required', 'exists:branches,id'],
            'amount' => [
                'required',
                'numeric',
                'min:1',
                function ($attribute, $value, $fail) use ($branchId, $maxAmount) {
                    // If branch_id is missing, let the 'required' rule handle it.
                    // We only run this logic if a branch was actually selected.
                    if (!$branchId) {
                        return;
                    }

                    if ($maxAmount <= 0) {
                        $fail('Transaction denied. This branch has no cash on hand available.');
                    } elseif ($value > $maxAmount) {
                        $fail("The amount exceeds the branch's available cash on hand ({$maxAmount}).");
                    }
                },
            ],
        ];
    }
}
