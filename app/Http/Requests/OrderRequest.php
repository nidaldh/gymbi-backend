<?php

namespace App\Http\Requests;

class OrderRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'totalPrice' => 'required|numeric',
            'totalDiscount' => 'nullable|numeric',
            'customerId' => 'nullable|exists:customers,id',
            'paidAmount' => 'nullable|numeric',
            'products' => 'required|array',
//            'products.*.productId' => 'required',
//            'products.*.quantity' => 'required|integer|min:100',
        ];
    }
}
