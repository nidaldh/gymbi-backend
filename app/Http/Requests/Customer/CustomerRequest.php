<?php

namespace App\Http\Requests\Customer;

use App\Http\Requests\BaseRequest;

class CustomerRequest extends BaseRequest
{

    public function rules(): array
    {
        return [
            'name' => 'required|string',
            'phoneNumber' => 'required|string',
        ];
    }

    public function messages()
    {
        return [
            'name.required' => 'The name is required.',
            'name.string' => 'The name must be a string.',
            'phoneNumber.required' => 'The phone number is required.',
            'phoneNumber.string' => 'The phone number must be a string.',
        ];
    }
}
