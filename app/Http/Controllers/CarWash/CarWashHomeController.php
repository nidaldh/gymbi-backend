<?php

namespace App\Http\Controllers\CarWash;

use App\Http\Controllers\Controller;
use App\Models\Expense\ExpenseModel;
use App\Models\Order\OrderModel;
use Carbon\Carbon;

class CarWashHomeController extends Controller
{
    public function getSalesData()
    {
        $storeId = auth()->user()->store_id;
        $startOfDay = Carbon::now()->startOfDay()->toDateTimeString();
        $endOfDay = Carbon::now()->endOfDay()->toDateTimeString();

        $totalSales = OrderModel::where('store_id', $storeId)
            ->whereBetween('created_at', [$startOfDay, $endOfDay])
            ->sum('totalPrice');

        $soldProducts = OrderModel::where('store_id', $storeId)
            ->whereBetween('created_at', [$startOfDay, $endOfDay])
            ->with('orderProducts')
            ->get()
            ->flatMap(function ($order) {
                return $order->orderProducts;
            })->groupBy('productId')
            ->map(function ($products) {
                return [
                    'productId' => $products->first()->productId,
                    'name' => $products->first()->name,
                    'quantity' => $products->sum('quantity'),
                ];
            })->sortByDesc('quantity')
            ->values();


        $totalExpenses = ExpenseModel::where('store_id', $storeId)
            ->whereBetween('date', [$startOfDay, $endOfDay])
            ->sum('total');

        $expanses = ExpenseModel::where('store_id', $storeId)
            ->whereBetween('date', [$startOfDay, $endOfDay])
            ->get()
            ->map(function ($expense) {
                return [
                    'id' => $expense->id,
                    'name' => $expense->name,
                    'total' => $expense->total,
                    'paid_amount' => $expense->paid_amount,
                ];
            })->sortByDesc('total')
            ->values();
        return response()->json(
            [
                'totalSales' => $totalSales,
                'soldProducts' => $soldProducts,
                'totalExpenses' => $totalExpenses,
                'expanses' => $expanses
            ]
        );
    }
}
