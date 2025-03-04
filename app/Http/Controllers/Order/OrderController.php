<?php

namespace App\Http\Controllers\Order;

use App\Enums\StoreType;
use App\Http\Controllers\Controller;
use App\Http\Requests\OrderRequest;
use App\Models\Order\OrderModel;
use App\Models\Order\OrderProductModel;
use App\Models\Order\OrderTransactionModel;
use App\Models\Product\ProductModel;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Excel;
use Illuminate\Support\Facades\DB;

class OrderController extends Controller
{
    protected $excel;

    public function __construct(Excel $excel)
    {
        $this->excel = $excel;
    }


    public function addOrder(OrderRequest $request)
    {
        $store_id = auth()->user()->store_id;

        DB::beginTransaction();

        try {
            $order = new OrderModel([
                'old_id' => $request->old_id,
                'totalPrice' => $request->totalPrice,
                'totalDiscount' => $request->totalDiscount,
                'customerId' => $request->customerId,
                'paidAmount' => $request->paidAmount,
                'store_id' => $store_id,
            ]);

            $order->save();

            $totalCost = 0;
            $productErrors = [];
            $storeType = auth()->user()->store->store_type;
            foreach ($request->products as $productData) {
                $product_id = $productData['productId'];
                $product = ProductModel::find($product_id);

                if (!$product) {
                    $product = ProductModel::where('id', $product_id)->first();
                    if (!$product) {
                        $productErrors[$productData['productId']] = 'المنتج غير موجود';
                        continue;
                    }
                    $product_id = $product->id;
                }

                if ($product->quantity < $productData['quantity'] && !(($storeType == StoreType::CAR_WASH || $storeType == StoreType::RESTAURANT) && !$product->countable)) {
                    $productErrors[$productData['productId']] = 'كمية المنتج غير كافية';
                    continue;
                }
                $product_name = $product->name;

                if ($storeType == StoreType::TIRES) {
                    $product_name = $product->name . '/ ' . $product->status . ' /' . $product->brandName;
                }

                $orderProduct = new OrderProductModel([
                    'order_id' => $order->id,
                    'productId' => $product_id,
                    'name' => $product_name,
                    'quantity' => $productData['quantity'],
                    'price' => $productData['price'],
                    'costPrice' => $product->costPrice,
                ]);

                $orderProduct->save();

                if (!(($storeType == StoreType::CAR_WASH || $storeType == StoreType::RESTAURANT) && !$product->countable)) {
                    $product->quantity -= $productData['quantity'];
                }
                $product->save();
                $totalCost += $product->costPrice * $productData['quantity'];
            }

            if (!empty($productErrors)) {
                DB::rollBack();
                return response()->json(['errors' => ['products' => $productErrors]], 422);
            }

            $order->update(['totalCost' => $totalCost]);

            if ($request->paidAmount) {
                $orderTransaction = new OrderTransactionModel([
                    'amount' => $request->paidAmount,
                    'date' => Carbon::now(),
                    'order_id' => $order->id,
                ]);

                $orderTransaction->save();
            }

            DB::commit();

            return response()->json(['message' => 'تم إضافة الطلب بنجاح', 'order' => $order], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'حدث خطأ أثناء إضافة الطلب'], 500);
        }
    }

    public function getOrders()
    {
        $storeId = auth()->user()->store_id;
        $orders = OrderModel::where('store_id', $storeId)
            ->with('customer:id,name')
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($order) {
                return [
                    'id' => $order->id,
                    'totalPrice' => (double)$order->totalPrice,
                    'totalDiscount' => (double)$order->totalDiscount,
                    'customerName' => $order->customer ? $order->customer->name : null,
                    'paidAmount' => (double)$order->paidAmount,
                    'created_at' => $order->created_at,
                    'totalCost' => $order->totalCost ?? 0,
                ];
            });

        return response()->json(['orders' => $orders], 200);
    }

    public function getOrderDetails($id)
    {
        $order = OrderModel::with(['customer:id,name', 'orderProducts', 'orderTransactions'])
            ->findOrFail($id);

        $orderDetails = [
            'id' => $order->id,
            'totalPrice' => (double)$order->totalPrice,
            'totalDiscount' => (double)$order->totalDiscount,
            'customerName' => $order->customer ? $order->customer->name : null,
            'paidAmount' => (double)$order->paidAmount,
            'created_at' => $order->created_at,
            'totalCost' => $order->totalCost ?? 0,
            'products' => $order->orderProducts->map(function ($product) {
                return [
                    'productId' => $product->productId,
                    'name' => $product->name,
                    'quantity' => $product->quantity,
                    'price' => $product->price,
                    'costPrice' => $product->costPrice,
                ];
            }),
            'transactions' => $order->orderTransactions->map(function ($transaction) {
                return [
                    'amount' => $transaction->amount,
                    'date' => $transaction->date,
                    'id' => $transaction->id,
                ];
            }),
        ];

        return response()->json($orderDetails, 200);
    }

    public function deleteOrder($id)
    {
        $order = OrderModel::with('orderProducts')->findOrFail($id);

        foreach ($order->orderProducts as $orderProduct) {
            $product = ProductModel::find($orderProduct->productId);
            if (!$product) {
                $product = ProductModel::where('id', $orderProduct->productId)->first();
            }
            if ($product) {
                $newQuantity = $product->quantity + $orderProduct->quantity;
                $newCostPrice = (($product->quantity * $product->costPrice) + ($orderProduct->quantity * $orderProduct->costPrice)) / $newQuantity;

                $product->update([
                    'quantity' => $newQuantity,
                    'costPrice' => $newCostPrice,
                ]);
            }
        }

        $order->delete();

        return response()->json(['message' => 'تم حذف الطلب بنجاح'], 200);
    }

    public function updateOrder(Request $request, $id)
    {
        $request->validate([
            'totalPrice' => 'sometimes|required|numeric',
            'totalDiscount' => 'sometimes|nullable|numeric',
            'date' => 'sometimes|required|date',
            'customerId' => 'sometimes|nullable|exists:customers,id',
            'paidAmount' => 'sometimes|nullable|numeric',
        ]);

        $order = OrderModel::findOrFail($id);
        $order->update($request->all());

        return response()->json(['message' => 'تم تحديث الطلب بنجاح', 'order' => $order], 200);
    }


    function changeProductIdToId()
    {
        $order_products = OrderProductModel::with(
            [
                'order' => function ($query) {
                    $query->where('store_id', auth()->user()->store_id);
                }
            ]
        )
            ->get();
//        $order_products = DB::select('select * from order_products where order_id in (select id from orders where store_id = ?)', [auth()->user()->store_id]);

        foreach ($order_products as $order_product) {
            $product = ProductModel::find( $order_product->productId);
            if ($product) {
                $order_product->productId = $product->id;
                $order_product->save();
            }
        }
    }
}
