<?php

namespace App\Http\Controllers\V1\Product;

use App\Enums\StoreType;
use App\Http\Controllers\Controller;
use App\Http\Requests\BaseRequest;
use App\Http\Requests\Product\ProductRequest;
use App\Models\Order\OrderProductModel;
use App\Models\Product\ProductHistory;
use App\Models\Product\ProductModel;
use App\Models\Product\RetailShopProductModel;
use App\Models\Product\TiresAndRepairsProductModel;
use App\Models\Product\WarehouseProductModel;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Excel;

class ProductController extends Controller
{

    protected $excel;

    public function __construct(Excel $excel)
    {
        $this->excel = $excel;
    }

    public function store(ProductRequest $request)
    {
        $product = ProductModel::create($request->all());

        ProductHistory::create([
            'product_id' => $product->id,
            'store_id' => auth()->user()->store_id,
            'user_id' => auth()->user()->id,
            'description' => 'Product created, Details: ' . json_encode($request->all()),
            'type' => 'insert',
        ]);

        return response()->json(['product' => $product], 200);
    }

    public function index()
    {
        $products = ProductModel::orderBy('updated_at', 'desc')->get();

        return response()->json(['products' => $products]);
    }

    public function show($id)
    {
        $product = ProductModel::find($id);
        if (!$product) {
            return response()->json(['message' => 'Product not found'], 404);
        }

        return response()->json(['product' => $product], 200);
    }

    //update

    public function update(ProductRequest $request, $id)
    {
        $product = ProductModel::find($id);
        if (!$product) {
            return response()->json(['message' => 'Product not found'], 404);
        }
        $originalData = $product->getOriginal();
        $product->update($request->all());
        $updatedData = $product->getAttributes();
        unset($updatedData['updated_at']);
        unset($updatedData['created_at']);
        unset($updatedData['store_id']);
        unset($originalData['updated_at']);
        unset($originalData['created_at']);
        unset($originalData['store_id']);

        $diff = [];
        foreach ($originalData as $key => $value) {
            if (array_key_exists($key, $updatedData) && $updatedData[$key] != $value) {
                if (is_array($value) && is_array($updatedData[$key])) {
                    $diff[$key] = [
                        'old' => array_diff($value, $updatedData[$key]),
                        'new' => array_diff($updatedData[$key], $value)
                    ];
                } else {
                    $diff[$key] = ['old' => $value, 'new' => $updatedData[$key]];
                }
            }
        }

        if (!empty($diff)) {
            ProductHistory::create([
                'product_id' => $product->id,
                'store_id' => auth()->user()->store_id,
                'user_id' => auth()->user()->id,
                'description' => 'Product updated, Details: ' . json_encode($diff),
                'type' => 'update'
            ]);
        }

        return response()->json(['message' => 'Product updated successfully', 'product' => $product], 200);
    }

    public function destroy($id)
    {
        $product = ProductModel::find($id);

        if (!$product) {
            return response()->json(['message' => 'Product not found'], 404);
        }
        $product->delete();

        return response()->json(['message' => 'Product deleted successfully'], 200);
    }

    public function updateProductQuantityAndCost(BaseRequest $request, $id)
    {
        $request->validate([
            'quantity' => 'required|integer|min:1',
            'costPrice' => 'required|numeric',
        ]);

        $storeType = auth()->user()->store->store_type;

        if ($storeType === StoreType::RETAIL) {
            $product = RetailShopProductModel::find($id);
        } elseif ($storeType === StoreType::TIRES) {
            $product = TiresAndRepairsProductModel::find($id);
        } else {
            return response()->json(['message' => 'Invalid store type'], 400);
        }

        if (!$product) {
            return response()->json(['message' => 'Product not found'], 404);
        }

        $newQuantity = $product->quantity + $request->quantity;
        $newCostPrice = (($product->quantity * $product->costPrice) + ($request->quantity * $request->costPrice)) / $newQuantity;

        $product->update([
            'quantity' => $newQuantity,
            'costPrice' => $newCostPrice,
            'editedBy' => auth()->user()->id,
        ]);

        return response()->json(
            ['message' => 'Product quantity and cost updated successfully', 'product' => $product],
            200
        );
    }

    public function moveWarehouseProductToInventory(BaseRequest $request, $id)
    {
        $request->validate([
            'quantity' => 'required|integer|min:1',
        ]);

        $warehouseProduct = WarehouseProductModel::findOrFail($id);
        $product = ProductModel::findOrFail($warehouseProduct->productId);

        if ($warehouseProduct->quantity < $request->quantity) {
            return response()->json(['message' => 'Insufficient warehouse product quantity'], 422);
        }

        $newQuantity = $product->quantity + $request->quantity;
        $newCostPrice = (($product->quantity * $product->costPrice) + ($request->quantity * $warehouseProduct->costPrice)) / $newQuantity;

        $product->update([
            'quantity' => $newQuantity,
            'costPrice' => $newCostPrice,
        ]);

        $warehouseProduct->quantity -= $request->quantity;

        if ($warehouseProduct->quantity == 0) {
            $warehouseProduct->delete();
        } else {
            $warehouseProduct->save();
        }

        return response()->json(
            ['message' => 'Warehouse product moved to inventory successfully', 'product' => $product],
        );
    }

    public function getProductsSales(Request $request)
    {
        $startDate = $request->input('start_date') ?? now()->startOfMonth();
        $endDate = $request->input('end_date', now());
        $store_id = auth()->user()->store_id;

        $sales = OrderProductModel::whereHas('order', function ($query) use ($startDate, $endDate, $store_id) {
            $query->whereRaw('date(created_at) >= date(?)', [$startDate])
                ->whereRaw('date(created_at) <= date(?)', [$endDate]);
            $query->where('store_id', $store_id);
        })->selectRaw('productId, name, SUM(quantity) as total_quantity, SUM(price * quantity) as total_sales')
            ->groupBy('productId', 'name')
            ->orderByDesc('total_quantity')
            ->get();

        return response()->json(['sales' => $sales]);
    }

    public function getProductSales($id, Request $request)
    {
        $startDate = $request->input('start_date') ?? now()->startOfMonth();
        $endDate = $request->input('end_date', now());
        $store_id = auth()->user()->store_id;

        $sales = OrderProductModel::whereHas('order', function ($query) use ($startDate, $endDate, $store_id) {
            $query->whereRaw('date(created_at) >= date(?)', [$startDate])
                ->whereRaw('date(created_at) <= date(?)', [$endDate]);
            $query->where('store_id', $store_id);
        })
            ->where('productId', $id)
            ->selectRaw('quantity, (price * quantity) as total_sales,date(created_at) as date')
            ->orderByDesc('date')
            ->get();

        return response()->json(['sales' => $sales]);
    }


    public function getProductHistory($id)
    {
        $productHistory = ProductHistory::where('product_id', $id)
            ->orderBy('created_at', 'desc')->get();

        return response()->json(['productHistory' => $productHistory]);
    }
}
