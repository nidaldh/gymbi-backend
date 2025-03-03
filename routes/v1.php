<?php

use App\Http\Controllers\Order\OrderTransactionController;
use App\Http\Controllers\V1\CashController;
use App\Http\Controllers\V1\Check\CheckPayableController;
use App\Http\Controllers\V1\Check\CheckReceivableController;
use App\Http\Controllers\V1\CustomerController;
use App\Http\Controllers\V1\DashboardController;
use App\Http\Controllers\V1\ExpenseController;
use App\Http\Controllers\V1\Order\OrderController;
use App\Http\Controllers\V1\Order\PaymentController;
use App\Http\Controllers\V1\Product\ProductController;
use App\Http\Controllers\V1\Vendor\PurchaseController;
use App\Http\Controllers\V1\Vendor\VendorController;
use App\Http\Controllers\V1\Vendor\VendorPaymentController;


Route::prefix('v1')->group(function () {
    Route::middleware('auth:sanctum')->group(function () {
        Route::middleware('auth:sanctum')->prefix('customers')->group(function () {
            Route::get('/', [CustomerController::class, 'getCustomers']);
            Route::post('/', [CustomerController::class, 'addCustomer']);
            Route::put('/{id}', [CustomerController::class, 'updateCustomer']);
            Route::delete('/{id}', [CustomerController::class, 'deleteCustomer']);
            Route::get('/{id}/orders', [CustomerController::class, 'getCustomerOrders']);
            Route::get('/{id}', [CustomerController::class, 'getCustomerById']);
        });

        Route::prefix('orders')->group(function () {
            Route::post('/', [OrderController::class, 'addOrder']);
            Route::get('/', [OrderController::class, 'getOrders']);
            Route::get('/{id}', [OrderController::class, 'getOrderDetails']);
            Route::put('/{id}', [OrderController::class, 'updateOrder']);
            Route::delete('/{id}', [OrderController::class, 'deleteOrder']);
//    Route::post('/changeProductIdToId', [OrderController::class, 'changeProductIdToId']);
        });

        Route::prefix('orders/{order_id}/transactions')->group(function () {
            Route::post('/', [OrderTransactionController::class, 'addTransaction']);
            Route::get('/', [OrderTransactionController::class, 'getTransactions']);
            Route::put('/{id}', [OrderTransactionController::class, 'updateTransaction']);
            Route::post('/import', [OrderTransactionController::class, 'import']);
            Route::delete('/{id}', [OrderTransactionController::class, 'deleteTransaction']);
        });


        Route::group(['prefix' => 'customer'], function () {
            Route::post('{customerId}/payment', [PaymentController::class, 'addCustomerPayment']);
            Route::get('{customerId}/payments', [PaymentController::class, 'getCustomerPayments']);
            Route::delete('{customerId}/payment/{paymentId}', [PaymentController::class, 'deleteCustomerPayment']);
        });

        Route::group(['prefix' => 'vendor'], function () {
            Route::get('', [VendorController::class, 'index']);
            Route::get('{id}', [VendorController::class, 'show']);
            Route::post('', [VendorController::class, 'store']);
            Route::put('{id}', [VendorController::class, 'update']);

            Route::group(['prefix' => '{vendor_id}/purchase'], function () {
                Route::get('/', [PurchaseController::class, 'index']);
                Route::get('/{purchaseId}', [PurchaseController::class, 'show']);
                Route::post('/', [PurchaseController::class, 'store']);
                Route::put('/{purchaseId}', [PurchaseController::class, 'update']);
                Route::delete('/{purchaseId}', [PurchaseController::class, 'destroy']);
            });

            Route::group(['prefix' => '{id}/payment'], function () {
                Route::post('/', [VendorPaymentController::class, 'payVendor']);
                Route::get('/', [VendorPaymentController::class, 'getPayments']);
                Route::delete('/{paymentId}', [VendorPaymentController::class, 'deletePayment']);
            });
        });

        Route::group(['prefix' => 'expenses'], function () {
            Route::get('/', [ExpenseController::class, 'index']);
            Route::get('/categories', [ExpenseController::class, 'getExpensesCategories']);
            Route::get('/{id}', [ExpenseController::class, 'show']);
            Route::post('/', [ExpenseController::class, 'store']);
            Route::put('/{id}', [ExpenseController::class, 'update']);
            Route::delete('/{id}', [ExpenseController::class, 'destroy']);
            Route::post('/{expenseId}/payment', [ExpenseController::class, 'addPayment']);
            Route::delete('/{expenseId}/payment/{paymentId}', [ExpenseController::class, 'deletePayment']);
        });


        Route::prefix('checks')->group(function () {
            Route::prefix('receivable')->group(function () {
                Route::get('/', [CheckReceivableController::class, 'index']);
                Route::put('/{id}/status', [CheckReceivableController::class, 'updateStatus']);
                Route::put('/{id}/due-date', [CheckReceivableController::class, 'updateDueDate']);
                Route::get('/available', [CheckReceivableController::class, 'getAvailableChecks']);
            });
            Route::prefix('payable')->group(function () {
                Route::get('/', [CheckPayableController::class, 'index']);
                Route::put('/{id}/status', [CheckPayableController::class, 'updateStatus']);
                Route::put('/{id}/due-date', [CheckPayableController::class, 'updateDueDate']);
            });
        });

        Route::prefix('cash')->group(function () {
            Route::get('/', [CashController::class, 'index']);
            Route::post('/expenses', [CashController::class, 'spentCashOnExpenses']);
            Route::post('/purchase', [CashController::class, 'spentCashOnPurchases']);
            Route::post('/sales', [CashController::class, 'cashFromSales']);
        });

        Route::prefix('dashboard')->group(function () {
            Route::get('/', [DashboardController::class, 'index']);
            Route::get('/monthly-expenses', [DashboardController::class, 'getMonthlyExpenses']);
            Route::get('/monthly-sales', [DashboardController::class, 'getMonthlySales']);
            Route::get('/monthly-purchases', [DashboardController::class, 'getMonthlyPurchases']);
            Route::get('/total-products', [DashboardController::class, 'getTotalProducts']);
            Route::get('/total-vendors', [DashboardController::class, 'getTotalVendors']);
        });

        Route::prefix('products')->group(function () {
            Route::get('/', [ProductController::class, 'index']);
            Route::get('/sales', [ProductController::class, 'getProductsSales']);

            Route::get('/{id}', [ProductController::class, 'show']);
            Route::post('/', [ProductController::class, 'store']);
            Route::put('/{id}', [ProductController::class, 'update']);
            Route::delete('/{id}', [ProductController::class, 'destroy']);
            Route::get('/{id}/sales', [ProductController::class, 'getProductSales']);
            Route::get('/{id}/history', [ProductController::class, 'getProductHistory']);
            Route::put('/{id}/update-quantity-cost', [ProductController::class, 'updateProductQuantityAndCost']);
            Route::put(
                '/{id}/move-quantity-from-warehouse',
                [ProductController::class, 'moveWarehouseProductToInventory']
            );
        });
    });
});

