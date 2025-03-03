<?php

namespace App\Http\Requests\V1;

use App\Http\Requests\BaseRequest;

class OrderRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'totalPrice' => 'required|numeric',
            'totalDiscount' => 'nullable|numeric',
            'customerId' => 'nullable|exists:customers,id',
            'products' => 'required|array',
//            'products.*.productId' => 'required',
//            'products.*.quantity' => 'required|integer|min:100',
        ];
    }
}
