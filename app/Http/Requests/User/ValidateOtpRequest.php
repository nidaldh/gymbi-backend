<?php


namespace App\Http\Requests\User;

use App\Http\Requests\BaseRequest;

class ValidateOtpRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'mobile_number' => 'required|string|max:15',
            'otp' => 'required|string|size:6',
        ];
    }

    public function messages(): array
    {
        return [
            'mobile_number.required' => 'The mobile number is required.',
            'mobile_number.string' => 'The mobile number must be a string.',
            'mobile_number.max' => 'The mobile number may not be greater than 15 characters.',
            'otp.required' => 'The OTP is required.',
            'otp.string' => 'The OTP must be a string.',
            'otp.size' => 'The OTP must be exactly 6 characters.',
        ];
    }
}
