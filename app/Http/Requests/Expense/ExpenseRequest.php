<?php


namespace App\Http\Requests\Expense;


use App\Http\Requests\BaseRequest;

class ExpenseRequest extends BaseRequest
{

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'category' => 'required|string|max:255',
            'date' => 'required|date',
            'total' => 'required|numeric|min:0',
            'paid_amount' => 'nullable|numeric|min:0',
        ];
    }
}
