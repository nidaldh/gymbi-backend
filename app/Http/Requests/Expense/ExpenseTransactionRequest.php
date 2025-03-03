<?php


namespace App\Http\Requests\Expense;

use App\Http\Requests\BaseRequest;

class ExpenseTransactionRequest extends BaseRequest
{

    public function rules(): array
    {
        return [
            'amount' => 'required|numeric|min:0',
            'date' => 'required|date',
            'description' => 'nullable|string|max:255',
        ];
    }
}
