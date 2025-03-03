<?php

namespace App\Http\Controllers\Order;

use App\Http\Controllers\Controller;
use App\Imports\ProductsImport;
use App\Models\Order\OrderModel;
use App\Models\Order\OrderProductModel;
use App\Models\Product\ProductModel;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Excel;

class OrderProductController extends Controller
{

    protected $excel;

    public function __construct(Excel $excel)
    {
        $this->excel = $excel;
    }

    public function addOrderProduct(Request $request)
    {
        $request->validate([
            'order_id' => 'required|exists:orders,id',
            'productId' => 'required|string',
            'name' => 'required|string',
            'quantity' => 'required|integer',
            'price' => 'required|numeric',
            'costPrice' => 'required|numeric',
        ]);

        $orderProduct = new OrderProductModel([
            'order_id' => $request->order_id,
            'productId' => $request->productId,
            'name' => $request->name,
            'quantity' => $request->quantity,
            'price' => $request->price,
            'costPrice' => $request->costPrice,
        ]);

        $orderProduct->save();

        return response()->json(['message' => 'Order product added successfully', 'orderProduct' => $orderProduct],
            201);
    }

    public function getOrderProducts($orderId)
    {
        $orderProducts = OrderProductModel::where('order_id', $orderId)->get();

        return response()->json(['orderProducts' => $orderProducts], 200);
    }

    public function updateOrderProduct(Request $request, $id)
    {
        $request->validate([
            'productId' => 'sometimes|required|string',
            'name' => 'sometimes|required|string',
            'quantity' => 'sometimes|required|integer',
            'price' => 'sometimes|required|numeric',
            'costPrice' => 'sometimes|required|numeric',
        ]);

        $orderProduct = OrderProductModel::findOrFail($id);
        $orderProduct->update($request->all());

        return response()->json(['message' => 'Order product updated successfully', 'orderProduct' => $orderProduct],
            200);
    }

    public function deleteOrderProduct($id)
    {
        $orderProduct = OrderProductModel::findOrFail($id);
        $orderProduct->delete();

        return response()->json(['message' => 'Order product deleted successfully'], 200);
    }

    public function import(Request $request)
    {
        $request->validate(['file' => 'required|mimes:xlsx']);
        $file = $request->file('file');
        $fileData = $this->excel->toArray(new ProductsImport, $file);
        foreach ($fileData[0] as $row) {
            $orderProduct = new OrderProductModel($row);
            $order = OrderModel::where('old_id', $row['orderid'])->first();
            if (!$order) {
                continue;
            }
            if($row['productid'] == 'tz78pXzkfjFJT0eyidvh') {
                $row['productid'] = 'HWi2YJZUAm1V2jAJJIV9';
            }

            if($row['productid'] == 'oUFhuxZE3V9FCyOAPy75') {
                $row['productid'] = 'rn2cjENWmi7IGgsGbDgn';
            }

            if($row['productid'] == '0Jf6MQVvGs9O2ZdewFCk') {
                $row['productid'] = 'fO6e6bzM70ChxoLWy0Gl';
            }
//retail
            if($row['productid'] == 'gxwX3FNr9pAbC0ZcFaMd') {
                $row['productid'] = "0uizRfMz7MKGJ55ypCRo";
            }

            if($row['productid'] == 'KiRHza84RtLdXg3VEGWM') {
                $row['productid'] = "IqDFAsZANZSfIn8aJQmu";
            }
            if($row['productid'] == '3czqfLY1p14izGhakSVj') {
                $row['productid'] = "CRFi9bBHuIU0sjhwgvsp";
            }
            if($row['productid'] == 'PhxwF9NfI5vBlgyv6f8n') {
                $row['productid'] = "eRiiVcvrApZ81RLr2QKC";
            }
            if($row['productid'] == 'OsgvzDpvp06Ga9BcplLx') {
                $row['productid'] = "BauEJOWwmmS5IfsI1la9";
            }

            if($row['productid'] == 'MetWeTWUo9EUl5N5CE6s') {
                $row['productid'] = "SkQXMAKrYX57N5fGzAiK";
            }

            if($row['productid'] == 'oDvNyWCEmoF4L5vurDpo') {
                $row['productid'] = "tHVJRD9xGMCjnK1UCUxw";
            }

            if($row['productid'] == '9rJD0OwFBP2u0deh2HkA') {
                $row['productid'] = "xvNqhrXYODZt8QTcYNnZ";
            }

            if($row['productid'] == 'tePog3cTa2KMyp93Nwpg') {
                $row['productid'] = "8mf9aw3AVrt9FJXmbc6S";
            }

            if($row['productid'] == 'tdG7NUvSs9BVjdAulpsA') {
                $row['productid'] ="7nWLZ9sI0xHcAeM8IXHN";
            }
            if($row['productid'] == 'KGDxfJEAQJb99OXyondN') {
                $row['productid'] ="HilTozInGmZlLvUQZpW7";
            }

            $product = ProductModel::where('id', $row['productid'])->first();
            if (!$product) {
                dd($row, $order->id);
            }
            if($row['costprice'] == 0) {
                $row['costprice'] = $product->costPrice;
            }

            if ($orderProduct->where('productId', $product->id)->where('order_id', $order->id)->exists()) {
                $orderProduct->where('productId', $product->id)->where('order_id', $order->id)->update([
                    'name' => $row['productname'],
                    'quantity' => (int)$row['quantity'],
                    'price' => (double)$row['price'],
                    'costPrice' => (double)$row['costprice'],
                ]);
            } else {
                $orderProduct->order_id = $order->id;
                $orderProduct->productId = $product->id;
                $orderProduct->name = $row['productname'];
                $orderProduct->quantity = (int)$row['quantity'];
                $orderProduct->price = (double)$row['price'];
                $orderProduct->costPrice = (double)$row['costprice'];
                $orderProduct->save();
            }
        }
        return response()->json(['message' => 'Order products imported successfully'], 200);
    }
}
