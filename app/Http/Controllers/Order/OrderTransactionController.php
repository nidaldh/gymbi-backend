<?php

namespace App\Http\Controllers\Order;

use App\Http\Controllers\Controller;
use App\Imports\ProductsImport;
use App\Models\Order\OrderModel;
use App\Models\Order\OrderTransactionModel;
use Maatwebsite\Excel\Excel;

class OrderTransactionController extends Controller
{
    protected $excel;

    public function __construct(Excel $excel)
    {
        $this->excel = $excel;
    }

    public function addTransaction(Request $request, $order_id)
    {
        $request->validate([
            'amount' => 'required|numeric',
            'date' => 'required|date',
        ]);

        $transaction = new OrderTransactionModel([
            'amount' => $request->amount,
            'date' => $request->date,
            'order_id' => $order_id,
        ]);

        $transaction->save();

        $this->calculateOrderPaidAmount($order_id);

        return response()->json(['message' => 'Transaction added successfully', 'transaction' => $transaction], 200);
    }

    public function getTransactions($order_id)
    {
        $transactions = OrderTransactionModel::where('order_id', $order_id)->get();

        return response()->json(['transactions' => $transactions], 200);
    }

    public function updateTransaction(Request $request, $order_id, $id)
    {
        $request->validate([
            'amount' => 'sometimes|required|numeric',
            'date' => 'sometimes|required|date',
        ]);

        $transaction = OrderTransactionModel::where('order_id', $order_id)->findOrFail($id);
        $transaction->update($request->all());
        $this->calculateOrderPaidAmount($order_id);

        return response()->json(['message' => 'Transaction updated successfully', 'transaction' => $transaction]);
    }

    public function deleteTransaction($order_id, $id)//todo test.
    {
        $transaction = OrderTransactionModel::where('order_id', $order_id)->findOrFail($id);
        $transaction->delete();
        $this->calculateOrderPaidAmount($order_id);
        return response()->json(['message' => 'Transaction deleted successfully']);
    }

    public function import(Request $request, $order_id)
    {
        $request->validate(['file' => 'required|mimes:xlsx']);

        $file = $request->file('file');
        $fileData = $this->excel->toArray(new ProductsImport, $file);

        foreach ($fileData[0] as $row) {
            $transaction = new OrderTransactionModel($row);
            $order = OrderModel::where('old_id', $row['orderid'])->first();
            if ($transaction->where('order_id', $order->id)
                ->where('amount', '=', $row['paidamount'])
                ->where('date', $row['date'])
                ->exists()) {
                $transaction->where('order_id', $order->id)->where('amount', $row['paidamount'])
                    ->where('date', $row['date'])->update([
                        'amount' => $row['paidamount'],
                        'order_id' => $order->id,
                        'created_at' => $row['date'],
                    ]);
            } else {
                $transaction->create([
                    'amount' => $row['paidamount'],
                    'date' => $row['date'],
                    'created_at' => $row['date'],
                    'order_id' => $order->id,
                ]);
            }
        }

        return response()->json(['message' => 'Transactions imported successfully'], 200);
    }

    public function calculateOrderPaidAmount($order_id): void
    {
        $order = OrderModel::findOrFail($order_id);
        $totalPaidAmount = OrderTransactionModel::where('order_id', $order_id)->sum('amount');
        $order->update(['paidAmount' => $totalPaidAmount]);
    }
}
