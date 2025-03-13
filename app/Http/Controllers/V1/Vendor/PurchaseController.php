<?php

namespace App\Http\Controllers\V1\Vendor;

use App\Http\Controllers\Controller;
use App\Models\Product\ProductHistory;
use App\Models\Product\ProductModel;
use App\Models\Purchase\PurchaseOrderProductModel;
use App\Models\PurchaseModel;
use App\Models\VendorModel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PurchaseController extends Controller
{
    public function index($vendor_id)
    {
        $gym_id = auth()->user()->gym_id;
        $purchases = PurchaseModel::where('vendor_id', $vendor_id)
            ->where('gym_id', $gym_id)
            ->orderBy('created_at', 'desc')
            ->get();
        $purchases = $purchases->map(function ($purchase) {
            return [
                'id' => $purchase->id,
                'vendor_id' => $purchase->vendor_id,
                'total' => (double)$purchase->total,
                'sub_total' => (double)$purchase->sub_total,
                'discount' => (double)$purchase->discount,
                'notes' => $purchase->notes,
                'date' => $purchase->date
            ];
        });
        return response()->json(['purchases' => $purchases]);
    }

    public function listAllPurchase()
    {
        $gym_id = auth()->user()->gym_id;
        $purchases = PurchaseModel::where('gym_id', $gym_id)
            ->orderBy('date', 'desc')
            ->get();
        $purchases = $purchases->map(function ($purchase) {
            return [
                'id' => $purchase->id,
                'vendor_id' => $purchase->vendor_id,
                'total' => (double)$purchase->total,
                'unpaid_amount' => (double)$purchase->unpaid_amount,
                'notes' => $purchase->notes,
                'date' => $purchase->date
            ];
        });
        return response()->json(['purchases' => $purchases]);
    }

    public function show($vendor_id, $purchaseId)
    {
        $gym_id = auth()->user()->gym_id;
        $purchase = PurchaseModel::where('id', $purchaseId)
            ->where('vendor_id', $vendor_id)
            ->where('gym_id', $gym_id)
            ->with('products')->first();
        return response()->json(['purchase' => $purchase]);
    }

    public function store(Request $request, $vendor_id)
    {
        $request->validate([
            'total' => 'required|numeric',
            'notes' => 'nullable|string',
            'products' => 'nullable|array',
            'products.*.product_id' => 'nullable|string',
            'products.*.product_name' => 'required|string',
            'products.*.quantity' => 'required|numeric',
            'products.*.price' => 'required|numeric',
        ]);

        $gym_id = auth()->user()->gym_id;
        $vendor = VendorModel::where('id', $vendor_id)->where('gym_id', $gym_id)->first();
        if (!$vendor) {
            return response()->json(['message' => 'المورد غير موجود'], 404);
        }

        DB::beginTransaction();
        $old_products = [];
        try {
            $data = $request->all();
            $data['gym_id'] = $gym_id;
            $data['vendor_id'] = $vendor_id;
            $data['unpaid_amount'] = $request->total;
            $data['sub_total'] = $request->sub_total ?? $request->total;
            $vendor->update(['debt' => $vendor->debt + $request->total]);
            $purchase = PurchaseModel::create($data);

            if ($request->has('products')) {
                foreach ($request->products as $productData) {
                    if (isset($productData['product_id'])) {
                        $product = ProductModel::find($productData['product_id']);
                        if ($product) {
                            PurchaseOrderProductModel::create([
                                'purchase_order_id' => $purchase->id,
                                'product_id' => $productData['product_id'],
                                'product_name' => $productData['product_name'],
                                'quantity' => $productData['quantity'],
                                'price' => $productData['price'],
                            ]);

                            $newCostPrice = ($product->costPrice * $product->quantity + $productData['price'] * $productData['quantity']) / ($product->quantity + $productData['quantity']);
                            $newCostPrice = round($newCostPrice, 3);
                            $old_products[$product->id] = [
                                'quantity' => $product->quantity,
                                'costPrice' => $product->costPrice,
                            ];
                            $product->update([
                                'quantity' => $product->quantity + $productData['quantity'],
                                'costPrice' => $newCostPrice,
                            ]);
                            $description = 'تم شراء ' . $productData['quantity'] . ' بسعر ' . $productData['price'] . ' للقطعة' . "\n" . 'المورد: ' . $vendor->name;
                            ProductHistory::create([
                                'type' => 'purchase',
                                'product_id' => $product->id,
                                'description' => $description,
                                'gym_id' => $gym_id,
                                'user_id' => auth()->user()->id,
                            ]);
                        }
                    } else {
                        $new_product = ProductModel::create([
                            'name' => $productData['product_name'],
                            'quantity' => $productData['quantity'],
                            'costPrice' => $productData['price'],
                            'gym_id' => $gym_id,
                        ]);
                        PurchaseOrderProductModel::create([
                            'purchase_order_id' => $purchase->id,
                            'product_id' => $new_product->id,
                            'product_name' => $productData['product_name'],
                            'quantity' => $productData['quantity'],
                            'price' => $productData['price'],
                        ]);
                        $description = 'تم شراء  ' . $productData['product_name'] . ' بكمية ' . $productData['quantity'] . ' بسعر ' . $productData['price'] . ' للقطعة' . "\n" . 'المورد: ' . $vendor->name;

                        ProductHistory::create([
                            'type' => 'purchase',
                            'product_id' => $new_product->id,
                            'description' => $description,
                            'gym_id' => $gym_id,
                            'user_id' => auth()->user()->id,
                        ]);
                    }
                }
            }

            DB::commit();
            return response()->json($purchase, 201);
        } catch (\Exception $e) {
            DB::rollBack();
            foreach ($old_products as $key => $product) {
                ProductModel::where('_id', $product[$key])->update([
                    'quantity' => $product['quantity'],
                    'costPrice' => $product['costPrice'],
                ]);
            }
            return response()->json(['message' => 'خطأ في إنشاء الشراء: ' . $e->getMessage()], 500);
        }
    }

    public function update(Request $request, $vendor_id, $purchaseId)
    {
        $request->validate([
            'total' => 'sometimes|required|numeric',
            'notes' => 'nullable|string',
            'products' => 'nullable|array',
            'products.*.id' => 'nullable|exists:purchase_order_products,id',
            'products.*.product_id' => 'nullable|string',
            'products.*.product_name' => 'required|string',
            'products.*.quantity' => 'required|numeric',
            'products.*.price' => 'required|numeric',
        ]);

        $gym_id = auth()->user()->gym_id;
        $purchase = PurchaseModel::where('id', $purchaseId)
            ->where('gym_id', $gym_id)
            ->with('products')
            ->first();

        if (!$purchase) {
            return response()->json(['message' => 'الشراء غير موجود'], 404);
        }

        $vendor = VendorModel::where('id', $vendor_id)
            ->where('gym_id', $gym_id)
            ->first();

        if (!$vendor) {
            return response()->json(['message' => 'المورد غير موجود'], 404);
        }

        DB::beginTransaction();
        try {
            $debt = $vendor->debt - $purchase->total + $request->total;
            $vendor->update(['debt' => $debt]);

            $purchase->update([
                'total' => $request->total,
                'sub_total' => $request->sub_total ?? $request->total,
                'discount' => $request->discount ?? 0,
                'notes' => $request->notes,
            ]);

            if ($request->has('products')) {
                $existingProducts = $purchase->products->keyBy('id');
                $updatedProductIds = [];

                foreach ($request->products as $productData) {
                    if (isset($productData['id'])) {
                        // Update existing product
                        $purchaseProduct = $existingProducts->get($productData['id']);
                        if ($purchaseProduct) {
                            $product = ProductModel::find($productData['product_id']);
                            if ($product) {
                                $diff = '';
                                if ($purchaseProduct->quantity != $productData['quantity']) {
                                    $diff = 'الكمية من ' . $purchaseProduct->quantity . ' الى ' . $productData['quantity'] . "\n";
                                }
                                if ($purchaseProduct->price != $productData['price']) {
                                    $diff .= ' السعر من ' . $purchaseProduct->price . ' الى ' . $productData['price'];
                                }

                                $purchaseProduct->update([
                                    'quantity' => $productData['quantity'],
                                    'price' => $productData['price'],
                                ]);

                                if (!empty($diff)) {
                                    ProductHistory::create([
                                        'type' => 'purchase_update',
                                        'product_id' => $product->id,
                                        'description' => "  تم تعديل المنتج من طلبية الشراء: \n {$diff}",
                                        'gym_id' => $gym_id,
                                        'user_id' => auth()->user()->id,
                                    ]);
                                }
                            }
                        }
                        $updatedProductIds[] = $productData['id'];
                    }
                }

                foreach ($existingProducts as $existingProduct) {
                    if (!in_array($existingProduct->id, $updatedProductIds)) {
                        $product = ProductModel::find($existingProduct->product_id);
                        if ($product) {
                            $product->update([
                                'quantity' => $product->quantity - $existingProduct->quantity,
                            ]);

                            ProductHistory::create([
                                'type' => 'purchase_remove',
                                'product_id' => $product->id,
                                'description' => "تم حذف المنتج من الطلب: {$existingProduct->quantity} {$product->name}",
                                'gym_id' => $gym_id,
                                'user_id' => auth()->user()->id,
                            ]);
                        }
                        $existingProduct->delete();
                    }
                }
            }

            DB::commit();
            return response()->json($purchase->fresh('products'));
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error($e->getMessage());
            return response()->json(['message' => 'خطأ في تحديث الشراء: ' . $e->getMessage()], 500);
        }
    }

    public function destroy($vendor_id, $purchaseId)
    {
        $gym_id = auth()->user()->gym_id;
        $vendor = VendorModel::where('id', $vendor_id)
            ->where('gym_id', $gym_id)
            ->first();
        if (!$vendor) {
            return response()->json(['message' => 'المورد غير موجود'], 404);
        }
        $purchase = PurchaseModel::where('id', $purchaseId)
            ->where('vendor_id', $vendor_id)
            ->where('gym_id', $gym_id)
            ->with('products')
            ->first();

        if (!$purchase) {
            return response()->json(['message' => 'الشراء غير موجود'], 404);
        }

        DB::beginTransaction();
        try {
            $vendor->update(['debt' => $vendor->debt - $purchase->total]);

            foreach ($purchase->products as $product) {
                ProductHistory::create([
                    'type' => 'purchase_delete',
                    'product_id' => $product->product_id,
                    'description' => "تم حذف الطلبية الشراء التي تم من خلالها شراء المنتج",
                    'gym_id' => $gym_id,
                    'user_id' => auth()->user()->id,
                ]);
                $product->delete();
            }

            $purchase->delete();
            DB::commit();
            return response()->json([
                'message' => 'تم حذف الشراء بنجاح',
            ], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'خطأ في حذف الشراء: ' . $e->getMessage()], 500);
        }
    }
}
