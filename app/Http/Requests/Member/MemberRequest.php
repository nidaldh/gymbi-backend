<?php

namespace App\Http\Requests\Member;

use App\Http\Requests\BaseRequest;

class MemberRequest extends BaseRequest
{

    public function rules(): array
    {
        return [
            'name' => 'required|string',
            'mobile' => 'required|string',
            'date_of_birth' => 'required|date',
            'gender' => 'required|string',
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
