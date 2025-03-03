<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Http\Requests\User\LoginRequest;
use App\Http\Requests\User\RegisterUserRequest;
use App\Http\Requests\User\ValidateOtpRequest;
use App\Models\OtpModel;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{

    public function store(RegisterUserRequest $request)
    {
        $user = User::create([
            'name' => $request->name,
            'mobile_number' => $request->mobile_number,
            'password' => Hash::make($request->password),
        ]);

        $this->sendOtp($user->mobile_number);
        return $this->prepareUserInfo($user);
    }

    public function login(LoginRequest $request): JsonResponse
    {
        $request->validate([
            'mobile_number' => 'required|string',
            'password' => 'required|string',
        ]);

        $user = User::where('mobile_number', $request->mobile_number)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            return response()->json([
                'message' => 'Invalid credentials',
            ], 401);
        }

        $user->tokens()->delete();
        return $this->prepareUserInfo($user);
    }

    public function userStoreInfo(Request $request): JsonResponse
    {
        $user = auth()->user();

        $token = explode(' ', $request->header('Authorization'))[1];
        return $this->prepareUserInfo($user, $token);
    }

    public function validateOtp(ValidateOtpRequest $request): JsonResponse
    {
        $otpRecord = OtpModel::where('mobile_number', $request->mobile_number)
            ->where('otp', $request->otp)
            ->first();

        if ($otpRecord) {
            $otpRecord->delete();
            $user = User::where('mobile_number', $request->mobile_number)->first();
            if ($user) {
                $user->mobile_number_verified_at = Carbon::now();
                $user->save();

                $user->tokens()->delete();
                return $this->prepareUserInfo($user);
            }
        }

        return response()->json([
            'success' => false,
            'message' => 'Invalid OTP.',
        ], 422);
    }

    public function sendResetPasswordOtp(Request $request): JsonResponse
    {
        $request->validate([
            'mobile_number' => 'required|string|exists:users,mobile_number',
        ]);

        $this->sendOtp($request->mobile_number);

        return response()->json([
            'message' => 'OTP sent successfully.',
        ]);
    }

    public function resetPassword(Request $request): JsonResponse
    {
        $request->validate([
            'mobile_number' => 'required|string|exists:users,mobile_number',
            'otp' => 'required|string',
            'password' => 'required|string|confirmed',
        ]);

        $otpRecord = OtpModel::where('mobile_number', $request->mobile_number)
            ->where('otp', $request->otp)
            ->first();

        if ($otpRecord) {
            $otpRecord->delete();
            $user = User::where('mobile_number', $request->mobile_number)->first();
            if ($user) {
                $user->password = Hash::make($request->password);
                $user->save();

                return response()->json([
                    'message' => 'Password reset successfully.',
                ]);
            }
        }

        return response()->json(['message' => 'Invalid OTP.'], 422);
    }

    public function logout(): JsonResponse
    {
        auth()->user()->currentAccessToken()->delete();
        return response()->json(['message' => 'Logged out successfully']);
    }

    private function prepareUserInfo($user, $old_token = '')
    {
        if (!$old_token) {
            $user->tokens()->delete();
            $token = $user->createToken('auth_token')->plainTextToken;
        } else {
            $token = $old_token;
        }

        return response()->json([
            'user' => [
                'name' => $user->name,
                'mobile_number' => $user->mobile_number,
                'user_type' => $user->user_type,
            ],
            'store' => $user->store,
            'token' => $token,
        ]);
    }
}
