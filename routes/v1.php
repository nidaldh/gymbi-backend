<?php

use App\Http\Controllers\V1\CashController;
use App\Http\Controllers\V1\Check\CheckPayableController;
use App\Http\Controllers\V1\Check\CheckReceivableController;
use App\Http\Controllers\V1\MemberController;
use App\Http\Controllers\V1\DashboardController;
use App\Http\Controllers\V1\ExpenseController;
use App\Http\Controllers\V1\Order\OrderController;
use App\Http\Controllers\V1\Order\PaymentController;
use App\Http\Controllers\V1\Product\ProductController;
use App\Http\Controllers\V1\Subscription\SubscriptionTypeController;
use App\Http\Controllers\V1\Vendor\PurchaseController;
use App\Http\Controllers\V1\Vendor\VendorController;
use App\Http\Controllers\V1\Vendor\VendorPaymentController;


Route::prefix('v1')->group(function () {
    Route::middleware('auth:sanctum')->group(function () {
        Route::prefix('members')->group(function () {
            Route::get('/', [MemberController::class, 'index']);
            Route::post('/', [MemberController::class, 'addMember']);
            Route::put('/{id}', [MemberController::class, 'updateMember']);
            Route::delete('/{id}', [MemberController::class, 'deleteMember']);
            Route::get('/{id}/orders', [MemberController::class, 'getMemberOrders']);
            Route::get('/{id}', [MemberController::class, 'getMemberById']);
            Route::group(['prefix' => '{memberId}/subscriptions'], function () {
                Route::get('/', [MemberController::class, 'getMemberSubscriptions']);
                Route::post('/', [MemberController::class, 'addSubscription']);
                Route::delete('/{subscriptionId}', [MemberController::class, 'deleteSubscription']);
            });
        });

        Route::prefix('orders')->group(function () {
            Route::post('/', [OrderController::class, 'addOrder']);
            Route::get('/', [OrderController::class, 'getOrders']);
            Route::get('/{id}', [OrderController::class, 'getOrderDetails']);
            Route::put('/{id}', [OrderController::class, 'updateOrder']);
            Route::delete('/{id}', [OrderController::class, 'deleteOrder']);
//    Route::post('/changeProductIdToId', [OrderController::class, 'changeProductIdToId']);
        });

        Route::group(['prefix' => 'member'], function () {
            Route::post('{member_id}/payment', [PaymentController::class, 'addMemberPayment']);
            Route::get('{member_id}/payments', [PaymentController::class, 'getMemberPayments']);
            Route::delete('{member_id}/payment/{paymentId}', [PaymentController::class, 'deleteMemberPayment']);
        });

        Route::prefix('gyms')->group(function () {
            Route::group(['prefix' => 'subscription-types'], function () {
                Route::get('/', [SubscriptionTypeController::class, 'index']);
                Route::get('/{id}', [SubscriptionTypeController::class, 'show']);
                Route::post('/', [SubscriptionTypeController::class, 'store']);
                Route::put('/{id}', [SubscriptionTypeController::class, 'update']);
                Route::delete('/{id}', [SubscriptionTypeController::class, 'destroy']);
            });
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
            Route::put(
                '/{id}/move-quantity-from-warehouse',
                [ProductController::class, 'moveWarehouseProductToInventory']
            );
        });
    });
});

