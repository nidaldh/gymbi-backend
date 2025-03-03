<?php

use App\Http\Controllers\Common\BankController;
use App\Http\Controllers\Restaurant\RestaurantHomeController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\Order\OrderController;
use App\Http\Controllers\Order\OrderProductController;
use App\Http\Controllers\Order\OrderTransactionController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\StoreController;
use App\Http\Controllers\User\AuthController;
use App\Http\Controllers\WarehouseController;
use App\Http\Controllers\CarWash\CarWashHomeController;
use App\Http\Controllers\ExpenseController;

Route::middleware(['auth:sanctum'])->get('/user', function (Request $request) {
    return $request->user();
});

Route::middleware('auth:sanctum')->prefix('products')->group(function () {
    Route::get('/', [ProductController::class, 'index']);
    Route::get('/sales', [ProductController::class, 'getProductsSales']);

    Route::get('/{id}', [ProductController::class, 'show']);
    Route::post('/', [ProductController::class, 'store']);
    Route::put('/{id}', [ProductController::class, 'update']);
    Route::delete('/{id}', [ProductController::class, 'destroy']);
    Route::get('/{id}/sales', [ProductController::class, 'getProductSales']);

    Route::put('/{id}/update-quantity-cost', [ProductController::class, 'updateProductQuantityAndCost']);
    Route::put('/{id}/move-quantity-from-warehouse', [ProductController::class, 'moveWarehouseProductToInventory']);
});


// Route group for stores APIs
Route::middleware('auth:sanctum')->prefix('stores')->group(function () {
    Route::get('/', [StoreController::class, 'index']);
    Route::get('/{id}', [StoreController::class, 'show']);
    Route::post('/', [StoreController::class, 'store']);
    Route::put('/{id}', [StoreController::class, 'update']);
    Route::post('/{id}/product-attributes', [StoreController::class, 'updateAttributes']);
    Route::delete('/{id}', [StoreController::class, 'destroy']);
});

Route::middleware('auth:sanctum')->prefix('customers')->group(function () {
    Route::get('/', [CustomerController::class, 'getCustomers']);
    Route::post('/', [CustomerController::class, 'addCustomer']);
    Route::put('/{id}', [CustomerController::class, 'updateCustomer']);
    Route::delete('/{id}', [CustomerController::class, 'deleteCustomer']);
    Route::post('/import-customers', [CustomerController::class, 'import']);
    Route::get('/{id}/orders', [CustomerController::class, 'getCustomerOrders']);
    Route::get('/{id}', [CustomerController::class, 'getCustomerById']);
});


Route::middleware('auth:sanctum')->prefix('orders')->group(function () {
    Route::post('/', [OrderController::class, 'addOrder']);
    Route::get('/', [OrderController::class, 'getOrders']);
    Route::get('/{id}', [OrderController::class, 'getOrderDetails']);
//    Route::put('/{id}', [OrderController::class, 'updateOrder']);
    Route::delete('/{id}', [OrderController::class, 'deleteOrder']);
//    Route::post('/changeProductIdToId', [OrderController::class, 'changeProductIdToId']);
});

Route::prefix('auth')->group(function () {
    Route::post('/register', [AuthController::class, 'store']);
    Route::post('/validate-otp', [AuthController::class, 'validateOtp']);
    Route::post('/login', [AuthController::class, 'login']);
    Route::post('/send-reset-password-otp', [AuthController::class, 'sendResetPasswordOtp']);
    Route::post('/reset-password', [AuthController::class, 'resetPassword']);
    Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth:sanctum');
    Route::get('/user-store-info', [AuthController::class, 'userStoreInfo'])->middleware('auth:sanctum');
});

Route::middleware('auth:sanctum')->prefix('orders/{order_id}/transactions')->group(function () {
    Route::post('/', [OrderTransactionController::class, 'addTransaction']);
    Route::get('/', [OrderTransactionController::class, 'getTransactions']);
    Route::put('/{id}', [OrderTransactionController::class, 'updateTransaction']);
    Route::post('/import', [OrderTransactionController::class, 'import']);
    Route::delete('/{id}', [OrderTransactionController::class, 'deleteTransaction']);
});

Route::middleware('auth:sanctum')->prefix('warehouse-products')->group(function () {
    Route::get('/', [WarehouseController::class, 'index']);
    Route::get('/{id}', [WarehouseController::class, 'show']);
    Route::post('/', [WarehouseController::class, 'store']);
    Route::put('/{id}', [WarehouseController::class, 'update']);
    Route::delete('/{id}', [WarehouseController::class, 'destroy']);
});

Route::middleware('auth:sanctum')->prefix('order-products')->group(function () {
    Route::post('/', [OrderProductController::class, 'addOrderProduct']);
    Route::get('/{orderId}', [OrderProductController::class, 'getOrderProducts']);
    Route::put('/{id}', [OrderProductController::class, 'updateOrderProduct']);
    Route::delete('/{id}', [OrderProductController::class, 'deleteOrderProduct']);
    Route::post('/import-order-products', [OrderProductController::class, 'import']);
});

Route::middleware('auth:sanctum')->prefix('carwash-home')->group(function () {
    Route::get('/sales-data', [CarWashHomeController::class, 'getSalesData']);
});

Route::middleware('auth:sanctum')->prefix('restaurant-home')->group(function () {
    Route::get('/sales-data', [RestaurantHomeController::class, 'getSalesData']);
});


Route::middleware('auth:sanctum')->prefix('common')->group(function () {
    Route::get('/banks', [BankController::class, 'index']);
});

Route::middleware('auth:sanctum')->prefix('expenses')->group(function () {
    Route::get('/', [ExpenseController::class, 'index']);
    Route::get('/categories', [ExpenseController::class, 'getExpensesCategories']);
    Route::get('/{id}', [ExpenseController::class, 'show']);
    Route::post('/', [ExpenseController::class, 'store']);
    Route::put('/{id}', [ExpenseController::class, 'update']);
    Route::delete('/{id}', [ExpenseController::class, 'destroy']);
    Route::post('/{expenseId}/transactions', [ExpenseController::class, 'addTransaction']);
    Route::get('/{expenseId}/transactions', [ExpenseController::class, 'getTransactions']);
});


require __DIR__ . '/v1.php';
