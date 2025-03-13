<?php

namespace App\Http\Controllers\V1\Order;

use App\Enums\CheckStatusEnum;
use App\Http\Controllers\Controller;
use App\Http\Requests\V1\OrderRequest;
use App\Models\CashTransaction;
use App\Models\CheckReceivable;
use App\Models\MemberModel;
use App\Models\Order\OrderModel;
use App\Models\Order\OrderProductModel;
use App\Models\Product\ProductHistory;
use App\Models\Product\ProductModel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Excel;

class OrderController extends Controller
{
    protected $excel;

    public function __construct(Excel $excel)
    {
        $this->excel = $excel;
    }


    public function addOrder(OrderRequest $request)
    {
        $gym_id = auth()->user()->gym_id;

        DB::beginTransaction();
        $product_subtract_quantity = [];

        try {
            $order = new OrderModel([
                'totalPrice' => $request->totalPrice,
                'totalDiscount' => $request->totalDiscount,
                'member_id' => $request->member_id,
                'gym_id' => $gym_id,
            ]);

            $order->save();

            $totalCost = 0;
            $productErrors = [];
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

                if ($product->quantity < $productData['quantity'] && ($product->isCountable)) {
                    $productErrors[$productData['productId']] = 'كمية المنتج غير كافية';
                    continue;
                }
                $product_name = $productData['name'] ?: $product->name;

                $orderProduct = new OrderProductModel([
                    'order_id' => $order->id,
                    'productId' => $product_id,
                    'name' => $product_name,
                    'quantity' => $productData['quantity'],
                    'price' => $productData['price'],
                    'costPrice' => $product->costPrice,
                ]);

                $orderProduct->save();

                if ($product->isCountable) {
                    $product->quantity -= $productData['quantity'];
                    $product_subtract_quantity[$product_id] = $productData['quantity'];
                }
                $product->save();
                $totalCost += $product->costPrice * $productData['quantity'];
            }

            if (!empty($productErrors)) {
                DB::rollBack();
                return response()->json(['errors' => ['products' => $productErrors]], 422);
            }

            $order->update(['totalCost' => $totalCost]);


            if (!empty($request->paymentDetails)) {
                $this->handlePaymentDetails($request->paymentDetails, $order, $gym_id);
            } else {
                $order->update(['paidAmount' => 0, 'unpaid_amount' => $order->totalPrice]);
            }
            DB::commit();

            return response()->json(['message' => 'تم إضافة الطلب بنجاح', 'order' => $order], 200);
        } catch (\Exception $e) {
            Log::error($e);
            DB::rollBack();
            if (!empty($product_subtract_quantity)) {
                foreach ($product_subtract_quantity as $product_id => $quantity) {
                    $product = ProductModel::find($product_id);
                    $product->quantity += $quantity;
                    $product->save();
                }
            }
            return response()->json(['message' => 'حدث خطأ أثناء إضافة الطلب'], 500);
        }
    }

    private function handlePaymentDetails($paymentDetails, $order, $gym_id): void
    {
        $paid_amount = 0;

        if (!empty($paymentDetails['cash'])) {
            $cash_data = [
                'amount' => $paymentDetails['cash'],
                'gym_id' => $gym_id,
            ];
            if ($order->member_id) {
                $cash_data['customer_id'] = $order->member_id;
            } else {
                $cash_data['order_id'] = $order->id;
            }
            $cashTransaction = new CashTransaction($cash_data);
            $paid_amount += $paymentDetails['cash'];
            $cashTransaction->save();
        }

        if (!empty($paymentDetails['checks'])) {
            foreach ($paymentDetails['checks'] as $check) {
                $check_receivable = new CheckReceivable([
                    'check_number' => $check['check_number'],
                    'bank_id' => $check['bank_id'],
                    'issuer_name' => $check['issuer_name'],
                    'amount' => $check['amount'],
                    'status' => CheckStatusEnum::PENDING,
                    'due_date' => $check['due_date'],
                    'customer_id' => $order->member_id,
                    'gym_id' => $gym_id,
                ]);
                $paid_amount += $check['amount'];
                $check_receivable->save();
            }
        }

        if ($order->member_id) {
            $member = MemberModel::find($order->member_id);
            $member->debt += $order->totalPrice;
            $member->debt -= $paid_amount;
            $member->save();
        }
        if ($paid_amount > $order->totalPrice) {
            $order->update(['paidAmount' => $order->totalPrice, 'unpaid_amount' => 0]);
            $remaining_amount = $paid_amount - $order->totalPrice;
            $this->allocateRemainingAmountToOldOrders($order->member_id, $remaining_amount);
        } else {
            $order->update(
                ['paidAmount' => $paid_amount, 'unpaid_amount' => $order->totalPrice - $paid_amount]
            );
        }
    }

    private function allocateRemainingAmountToOldOrders($member_id, $remaining_amount): void
    {
        $old_orders = OrderModel::where('member_id', $member_id)
            ->where('unpaid_amount', '>', 0)
            ->orderBy('created_at', 'asc')
            ->get();

        foreach ($old_orders as $old_order) {
            if ($remaining_amount <= 0) {
                break;
            }

            $unpaid_amount = $old_order->unpaid_amount;
            if ($remaining_amount >= $unpaid_amount) {
                $old_order->update(
                    ['paidAmount' => $old_order->paidAmount + $unpaid_amount, 'unpaid_amount' => 0]
                );
                $remaining_amount -= $unpaid_amount;
            } else {
                $old_order->update(
                    [
                        'paidAmount' => $old_order->paidAmount + $remaining_amount,
                        'unpaid_amount' => $unpaid_amount - $remaining_amount
                    ]
                );
                $remaining_amount = 0;
            }
        }
    }

    public function getOrders(Request $request)
    {
        $startDate = $request->input('start_date') ?? now()->startOfYear();
        $endDate = $request->input('end_date');
        if (!empty($endDate)) {
            $endDate = $endDate . ' 23:59:59';
        } else {
            $endDate = now();
        }
        $gymId = auth()->user()->gym_id;
        $orders = OrderModel::where('gym_id', $gymId)
            ->with('member:id,name')
            ->whereBetween('created_at', [$startDate, $endDate])
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($order) {
                return [
                    'id' => $order->id,
                    'totalPrice' => (double)$order->totalPrice,
                    'totalDiscount' => (double)$order->totalDiscount,
                    'name' => $order->member ? $order->member->name : null,
                    'unpaid_amount' => $order->unpaid_amount,
                    'created_at' => $order->created_at,
                    'totalCost' => $order->totalCost ?? 0,
                ];
            });

        return response()->json(['orders' => $orders], 200);
    }

    public function getOrderDetails($id)
    {
        $gymId = auth()->user()->gym_id;
        $order = OrderModel::with(['member:id,name', 'orderProducts'])
            ->where('gym_id', $gymId)
            ->findOrFail($id);

        $orderDetails = [
            'id' => $order->id,
            'totalPrice' => (double)$order->totalPrice,
            'totalDiscount' => (double)$order->totalDiscount,
            'name' => $order->member ? $order->member->name : null,
            'unpaid_amount' => $order->unpaid_amount,
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
        ];

        return response()->json($orderDetails, 200);
    }

    public function deleteOrder($id)
    {
        $gym_id = auth()->user()->gym_id;
        $order = OrderModel::with(['orderProducts', 'cashTransactions', 'member'])
            ->where('gym_id', $gym_id)
            ->findOrFail($id);

        DB::beginTransaction();
        $old_product_info = [];
        try {
            foreach ($order->orderProducts as $orderProduct) {
                $product = ProductModel::find($orderProduct->productId);
                if (!$product) {
                    $product = ProductModel::where('id', $orderProduct->productId)->first();
                }
                if ($product) {
                    $newQuantity = $product->quantity + $orderProduct->quantity;
                    $newCostPrice = (($product->quantity * $product->costPrice) + ($orderProduct->quantity * $orderProduct->costPrice)) / $newQuantity;

                    $old_product_info[$product->id] = [
                        'quantity' => $product->quantity,
                        'costPrice' => $product->costPrice
                    ];
                    $old_product_data = ['quantity' => $product->quantity, 'costPrice' => $product->costPrice];
                    $new_product_data = ['quantity' => $newQuantity, 'costPrice' => $newCostPrice];
                    $product->update($new_product_data);
                    $diff = "مسح طلبية : ";

                    foreach ($old_product_data as $key => $value) {
                        if (array_key_exists($key, $new_product_data) && $new_product_data[$key] != $value) {
                            $diff .= "$key: $value -> $new_product_data[$key]";
                        }
                    }

                    ProductHistory::create([
                        'product_id' => $product->id,
                        'gym_id' => $gym_id,
                        'description' => $diff,
                        'user_id' => auth()->user()->id,
                        'type' => 'update'
                    ]);
                }
            }

            $order->cashTransactions()->delete();
            if ($order->member_id) {
                $member = MemberModel::find($order->member_id);
                $member->debt -= $order->totalPrice;
                $member->save();
            }

            $order->delete();
            DB::commit();

            return response()->json(['message' => 'تم حذف الطلب بنجاح'], 200);
        } catch (\Exception $e) {
            Log::error($e);
            foreach ($old_product_info as $product_id => $info) {
                $product = ProductModel::find($product_id);
                $product->update($info);
            }
            DB::rollBack();
            return response()->json(['message' => 'حدث خطأ أثناء حذف الطلب'], 500);
        }
    }

    public function updateOrder(OrderRequest $request, $id)
    {
        $gym_id = auth()->user()->gym_id;
        DB::beginTransaction();

        try {
            $order = OrderModel::with(['orderProducts', 'member'])
                ->where('gym_id', $gym_id)
                ->findOrFail($id);
            $old_total_price = $order->totalPrice;

            $order->update([
                'totalPrice' => $request->totalPrice,
                'totalDiscount' => $request->totalDiscount,
            ]);

            if ($order->member_id) {
                $member = MemberModel::find($order->member_id);

                $total_diff = $order->totalPrice - $old_total_price;
                if ($total_diff > 0) {
                    $member->debt += $total_diff;
                } else {
                    $member->debt -= abs($total_diff);
                    $this->allocateRemainingAmountToOldOrders($order->member_id, abs($total_diff));
                }
                $member->save();
            } else {
                $total_diff = $order->totalPrice - $old_total_price;
                if ($total_diff < 0) {
                    CashTransaction::create([
                        'amount' => $total_diff,
                        'gym_id' => $gym_id,
                        'order_id' => $order->id,
                        'notes' => 'تحديث الطلب'
                    ]);
                } else {
                    return response()->json(['message' => 'لا يمكن تحديث الطلب بقيمة أكبر'], 422);
                }
            }

            $existingProducts = collect($order->orderProducts)->keyBy('productId');
            $newProducts = collect($request->products)->keyBy('productId');

            $totalCost = 0;
            $productErrors = [];

            // Process quantity changes for all products
            foreach ($newProducts as $productId => $productData) {
                $product = ProductModel::find($productId);

                if (!$product) {
                    $productErrors[$productId] = 'المنتج غير موجود';
                    continue;
                }

                $oldQuantity = isset($existingProducts[$productId]) ? $existingProducts[$productId]->quantity : 0;
                $newQuantity = $productData['quantity'];
                $quantityDiff = $newQuantity - $oldQuantity;

                if ($product->isCountable && $quantityDiff > 0 && $product->quantity < $quantityDiff) {
                    $productErrors[$productId] = 'كمية المنتج غير كافية';
                    continue;
                }

                if ($product->isCountable && $quantityDiff != 0) {
                    $oldProductQuantity = $product->quantity;
                    $product->quantity -= $quantityDiff;
                    $product->save();

                    ProductHistory::create([
                        'product_id' => $product->id,
                        'gym_id' => $gym_id,
                        'description' => "تحديث طلبية : تغيير الكمية من {$oldProductQuantity} إلى {$product->quantity}",
                        'user_id' => auth()->user()->id,
                        'type' => 'update'
                    ]);
                }

                $totalCost += $product->costPrice * $newQuantity;
            }

            foreach ($existingProducts as $productId => $oldProduct) {
                if (!$newProducts->has($productId)) {
                    $product = ProductModel::find($productId);
                    if ($product && $product->isCountable) {
                        $oldQuantity = $product->quantity;
                        $product->quantity += $oldProduct->quantity;
                        $product->save();

                        ProductHistory::create([
                            'product_id' => $product->id,
                            'gym_id' => $gym_id,
                            'description' => "إلغاء منتج من الطلبية : تغيير الكمية من {$oldQuantity} إلى {$product->quantity}",
                            'user_id' => auth()->user()->id,
                            'type' => 'update'
                        ]);
                    }
                }
            }

            if (!empty($productErrors)) {
                DB::rollBack();
                return response()->json(['errors' => ['products' => $productErrors]], 422);
            }

            // Delete old products and add new ones
            $order->orderProducts()->delete();
            foreach ($request->products as $productData) {
                $product = ProductModel::find($productData['productId']);
                OrderProductModel::create([
                    'order_id' => $order->id,
                    'productId' => $productData['productId'],
                    'name' => $productData['name'] ?: $product->name,
                    'quantity' => $productData['quantity'],
                    'price' => $productData['price'],
                    'costPrice' => $product->costPrice,
                ]);
            }

            $order->update([
                'totalCost' => $totalCost
            ]);


            DB::commit();
            return response()->json([
                'message' => 'تم تحديث الطلب بنجاح',
                'order' => $order->fresh(['orderProducts', 'member'])
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error($e);
            return response()->json(['message' => 'حدث خطأ أثناء تحديث الطلب'], 500);
        }
    }
}
