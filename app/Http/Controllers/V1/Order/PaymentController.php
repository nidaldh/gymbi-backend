<?php

namespace App\Http\Controllers\V1\Order;

use App\Enums\CheckStatusEnum;
use App\Http\Controllers\Controller;
use App\Models\CashTransaction;
use App\Models\CheckReceivable;
use App\Models\CustomerModel;
use App\Models\Order\OrderModel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PaymentController extends Controller
{
    public function addCustomerPayment(Request $request, $customerId)
    {
        $request->validate([
//            'paymentDetails.cash' => 'nullable|numeric',
//            'paymentDetails.checks' => 'nullable|array',
//            'paymentDetails.checks.*.check_number' => 'required_with:paymentDetails.checks|string',
//            'paymentDetails.checks.*.bank_id' => 'required_with:paymentDetails.checks|integer|exists:banks,id',
//            'paymentDetails.checks.*.issuer_name' => 'required_with:paymentDetails.checks|string',
//            'paymentDetails.checks.*.amount' => 'required_with:paymentDetails.checks|numeric',
//            'paymentDetails.checks.*.due_date' => 'required_with:paymentDetails.checks|date',
        ]);

        DB::beginTransaction();
        $customer = CustomerModel::find($customerId);
        if (!$customer) {
            return response()->json(['message' => 'Customer not found'], 404);
        }

        try {
            $paid_amount = 0;
            $store_id = auth()->user()->store_id;

            if (!empty($request->paymentDetails['cash'])) {
                $cashTransaction = new CashTransaction([
                    'customer_id' => $customerId,
                    'amount' => $request->paymentDetails['cash'],
                    'store_id' => $store_id,
                ]);
                $paid_amount += $request->paymentDetails['cash'];
                $cashTransaction->save();
            }

            if (!empty($request->paymentDetails['checks'])) {
                foreach ($request->paymentDetails['checks'] as $check) {
                    $check_receivable = new CheckReceivable([
                        'check_number' => $check['check_number'],
                        'bank_id' => $check['bank_id'],
                        'issuer_name' => $check['issuer_name'],
                        'amount' => $check['amount'],
                        'status' => CheckStatusEnum::PENDING,
                        'due_date' => $check['due_date'],
                        'customer_id' => $customerId,
                        'store_id' => $store_id,
                    ]);
                    $paid_amount += $check['amount'];
                    $check_receivable->save();
                }
            }

            $customer->debt -= $paid_amount;
            $customer->save();

            $this->allocateRemainingAmountToOldOrders($customerId, $paid_amount);

            DB::commit();

            return response()->json(['message' => 'Payment added successfully', 'paid_amount' => $paid_amount], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Error adding payment', 'error' => $e->getMessage()], 500);
        }
    }

    private function allocateRemainingAmountToOldOrders($customerId, $remaining_amount): void
    {
        $old_orders = OrderModel::where('customerId', $customerId)
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

    //list all payments of a customer

    public function getCustomerPayments($customerId)
    {
        $cashTransactions = CashTransaction::where('customer_id', $customerId)->select(
            'id',
            'amount',
            'created_at'
        )->get()->map(function ($transaction) {
            return [
                'id' => $transaction->id,
                'type' => 'cash',
                'amount' => (double)$transaction->amount,
                'created_at' => $transaction->created_at,
            ];
        });

        $checkReceivables = CheckReceivable::where('customer_id', $customerId)
            ->get()->map(function ($transaction) {
                return [
                    'id' => $transaction->id,
                    'type' => 'receivable_check',
                    'check_number' => $transaction->check_number,
                    'issuer_name' => $transaction->issuer_name,
                    'amount' => (double)$transaction->amount,
                    'status' => $transaction->status,
                    'due_date' => $transaction->due_date,
                    'created_at' => $transaction->created_at,
                    'bank' => $transaction->bank->name_ar,
                ];
            });

        $payments = $cashTransactions->concat($checkReceivables)->sortBy('created_at')->values();


        return response()->json(['payments' => $payments], 200);
    }

    public function deleteCustomerPayment($customerId, $paymentId)
    {
        $store_id = auth()->user()->store_id;
        $payment = CashTransaction::where('customer_id', $customerId)
            ->where('store_id', $store_id)
            ->find($paymentId);
        if (!$payment) {
            return response()->json(['message' => 'Payment not found'], 404);
        }

        DB::beginTransaction();
        try {
            if ($payment->checkReceivable) {
                if ($payment->checkReceivable->status == CheckStatusEnum::PENDING) {
                    $payment->checkReceivable->delete();
                } else {
                    return response()->json(['message' => 'Cannot delete payment'], 400);
                }
            }

            $customer = CustomerModel::find($customerId);
            $customer->debt += $payment->amount;
            $customer->save();

            $payment->delete();
            DB::commit();
            return response()->json(['message' => 'Payment deleted successfully'], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Error deleting payment', 'error' => $e->getMessage()], 500);
        }
    }


}
