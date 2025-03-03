<?php

namespace App\Http\Controllers;

use App\Models\OtpModel;
use App\Utils\WhatsAppUtil;
use Illuminate\Support\Facades\Log;

abstract class Controller
{
    protected function sendOtp(string $mobileNumber): void
    {
        $otp = rand(100000, 999999);

        OtpModel::create([
            'mobile_number' => $mobileNumber,
            'otp' => $otp,
        ]);
        if (env('APP_DEBUG', true)) {
            return;
        }
        $whatAppUtil = new WhatsAppUtil();

        try {
            if (!str_starts_with($mobileNumber, '+')) {
                $mobileNumber = '+' . $mobileNumber;
            } else {
                if (str_starts_with($mobileNumber, '00')) {
                    $mobileNumber = '+' . substr($mobileNumber, 2);
                }
            }
            $body = 'اهلا بك في تطبيق دوري، رمز التحقيق هو: ' . $otp;
            $whatAppUtil->sendMessage($mobileNumber, $body);
        } catch (\Exception $e) {
            Log::error('Error sending OTP: ' . $e->getMessage());
        }
    }
}
