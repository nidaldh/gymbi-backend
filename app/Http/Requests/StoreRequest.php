<?php

namespace App\Http\Requests;


class StoreRequest extends BaseRequest
{

    public function rules(): array
    {
        return [
            'store_name' => 'required|string|max:255',
        ];
    }
}
