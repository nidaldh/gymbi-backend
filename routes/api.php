<?php

use App\Http\Controllers\Common\BankController;
use App\Http\Controllers\GymController;
use App\Http\Controllers\User\AuthController;
use App\Http\Controllers\V1\HomeDashboardController;

Route::middleware(['auth:sanctum'])->get('/user', function (Request $request) {
    return $request->user();
});

Route::middleware('auth:sanctum')->prefix('gyms')->group(function () {
    Route::get('/dashboard-stats', [HomeDashboardController::class, 'getDashboardStats']);
    Route::get('/', [GymController::class, 'index']);
    Route::get('/{id}', [GymController::class, 'show']);
    Route::post('/', [GymController::class, 'store']);
    Route::put('/{id}', [GymController::class, 'update']);
    Route::post('/{id}/product-attributes', [GymController::class, 'updateAttributes']);
    Route::delete('/{id}', [GymController::class, 'destroy']);
});

Route::prefix('auth')->group(function () {
    Route::post('/register', [AuthController::class, 'store']);
    Route::post('/validate-otp', [AuthController::class, 'validateOtp']);
    Route::post('/login', [AuthController::class, 'login']);
    Route::post('/send-reset-password-otp', [AuthController::class, 'sendResetPasswordOtp']);
    Route::post('/reset-password', [AuthController::class, 'resetPassword']);
    Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth:sanctum');
    Route::get('/user-gym-info', [AuthController::class, 'userGymInfo'])->middleware('auth:sanctum');
});

Route::middleware('auth:sanctum')->prefix('common')->group(function () {
    Route::get('/banks', [BankController::class, 'index']);
});


require __DIR__ . '/v1.php';
