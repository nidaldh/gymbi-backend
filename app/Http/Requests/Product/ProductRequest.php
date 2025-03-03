<?php

namespace App\Http\Requests\Product;

use App\Enums\StoreType;
use App\Http\Requests\BaseRequest;

class ProductRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'name' => 'required|string',
            'costPrice' => 'required|numeric|min:0.00',
            'salePrice' => 'required|numeric|min:0.01',
            'quantity' => 'nullable|integer|min:0',
        ];
    }
}
