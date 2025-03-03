<?php

namespace App\Http\Controllers\V1\Vendor;

use App\Enums\CheckStatusEnum;
use App\Http\Controllers\Controller;
use App\Models\CashTransaction;
use App\Models\CheckPayable;
use App\Models\CheckReceivable;
use App\Models\PurchaseModel;
use App\Models\VendorModel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class VendorPaymentController extends Controller
{
    public function payVendor(Request $request, $vendorId)
    {
        $request->validate([
//            'paymentDetails.cash' => 'nullable|numeric',
//            'paymentDetails.receivable_check_ids' => 'nullable|array',
//            'paymentDetails.receivable_check_ids.*' => 'required_with:paymentDetails.receivable_check_ids|integer|exists:check_receivable,id',
//            'paymentDetails.payable_checks' => 'nullable|array',
//            'paymentDetails.payable_checks.*.check_number' => 'required_with:paymentDetails.payable_checks|string',
//            'paymentDetails.payable_checks.*.bank_id' => 'required_with:paymentDetails.payable_checks|integer|exists:banks,id',
//            'paymentDetails.payable_checks.*.issuer_name' => 'required_with:paymentDetails.payable_checks|string',
//            'paymentDetails.payable_checks.*.amount' => 'required_with:paymentDetails.payable_checks|numeric',
//            'paymentDetails.payable_checks.*.due_date' => 'required_with:paymentDetails.payable_checks|date',
        ]);

        DB::beginTransaction();

        try {
            $vendor = VendorModel::findOrFail($vendorId);
            if (!$vendor) {
                return response()->json(['message' => 'Vendor not found'], 404);
            }

            $paid_amount = 0;
            $store_id = auth()->user()->store_id;

            if (!empty($request->paymentDetails['cash'])) {
                $cashTransaction = new CashTransaction([
                    'vendor_id' => $vendorId,
                    'amount' => -$request->paymentDetails['cash'],
                    'store_id' => $store_id,
                ]);
                $paid_amount += $request->paymentDetails['cash'];
                $cashTransaction->save();
            }

            if (!empty($request->paymentDetails['receivable_check_ids'])) {
                foreach ($request->paymentDetails['receivable_check_ids'] as $checkId) {
                    $check_receivable = CheckReceivable::findOrFail($checkId);

                    $cashTransaction = new CashTransaction([
                        'check_receivable_id' => $check_receivable->id,
                        'amount' => $check_receivable->amount,
                        'store_id' => $store_id,
                    ]);
                    $cashTransaction->save();

                    $cashTransaction = new CashTransaction([
                        'vendor_id' => $vendorId,
                        'amount' => -$check_receivable->amount,
                        'store_id' => $store_id,
                        'check_receivable_id' => $check_receivable->id,
                    ]);
                    $check_receivable->status = CheckStatusEnum::CLEARED;
                    $check_receivable->save();
                    $paid_amount += $check_receivable->amount;
                    $cashTransaction->save();
                }
            }

            if (!empty($request->paymentDetails['payable_checks'])) {
                foreach ($request->paymentDetails['payable_checks'] as $check) {
                    $check_payable = new CheckPayable([
                        'check_number' => $check['check_number'],
                        'bank_id' => $check['bank_id'],
                        'issuer_name' => $check['issuer_name'],
                        'amount' => $check['amount'],
                        'status' => CheckStatusEnum::PENDING,
                        'due_date' => $check['due_date'],
                        'vendor_id' => $vendorId,
                        'store_id' => $store_id,
                    ]);
                    $check_payable->save();
                    $paid_amount += $check['amount'];
                }
            }

            $this->allocateRemainingAmountToPurchases($vendorId, $paid_amount);
            $vendor->update(['debt' => $vendor->debt - $paid_amount]);

            DB::commit();

            return response()->json(['message' => 'Payment added successfully', 'paid_amount' => $paid_amount], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Error adding payment', 'error' => $e->getMessage()], 500);
        }
    }

    private function allocateRemainingAmountToPurchases($vendorId, $remaining_amount): void
    {
        $purchases = PurchaseModel::where('vendor_id', $vendorId)
            ->where('unpaid_amount', '>', 0)
            ->orderBy('date', 'asc')
            ->get();

        foreach ($purchases as $purchase) {
            if ($remaining_amount <= 0) {
                break;
            }

            $unpaid_amount = $purchase->unpaid_amount;
            if ($remaining_amount >= $unpaid_amount) {
                $purchase->update(
                    ['unpaid_amount' => 0]
                );
                $remaining_amount -= $unpaid_amount;
            } else {
                $purchase->update(
                    ['unpaid_amount' => $unpaid_amount - $remaining_amount]
                );
                $remaining_amount = 0;
            }
        }
    }

    public function getPayments($vendorId)
    {
        $store_id = auth()->user()->store_id;
        $payments = CashTransaction::where('vendor_id', $vendorId)
            ->where('store_id', $store_id)
            ->orderBy('created_at', 'desc')
            ->with(['checkReceivable'])
            ->get()
            ->map(function ($transaction) {
                if (!$transaction->checkReceivable) {
                    return [
                        'id' => $transaction->id,
                        'amount' => abs($transaction->amount),
                        'created_at' => $transaction->created_at,
                        'type' => 'cash',
                    ];
                }

                return [
                    'id' => $transaction->id,
                    'check_number' => $transaction->checkReceivable->check_number,
                    'issuer_name' => $transaction->checkReceivable->issuer_name,
                    'amount' => abs($transaction->checkReceivable->amount),
                    'due_date' => $transaction->checkReceivable->due_date,
                    'status' => $transaction->checkReceivable->status,
                    'bank' => $transaction->checkReceivable->bank->name_ar,
                    'type' => 'check_receivable',
                ];
            });

        $checkPayments = CheckPayable::where('vendor_id', $vendorId)
            ->where('store_id', $store_id)
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($check) {
                return [
                    'id' => $check->id,
                    'amount' => abs($check->amount),
                    'created_at' => $check->created_at,
                    'type' => 'check_payable',
                    'check_number' => $check->check_number,
                    'issuer_name' => $check->issuer_name,
                    'due_date' => $check->due_date,
                    'status' => $check->status,
                    'bank' => $check->bank->name_ar,
                ];
            });

        if (!empty($payments) && !empty($checkPayments)) {
            $payments = $payments->concat($checkPayments)->sortBy('created_at')->values();
        } else {
            if (!empty($checkPayments) && empty($payments)) {
                $payments = $checkPayments;
            }
        }
        return response()->json(['payments' => $payments]);
    }

    public function deletePayment($vendorId, $paymentId, Request $request)
    {
        $store_id = auth()->user()->store_id;

        if ($request->type == 'check_payable') {
            $payment = CheckPayable::where('vendor_id', $vendorId)
                ->where('store_id', $store_id)
                ->where('id', $paymentId)
                ->first();
        } else {
            $payment = CashTransaction::where('vendor_id', $vendorId)
                ->where('store_id', $store_id)
                ->where('id', $paymentId)
                ->first();
        }

        if (!$payment) {
            return response()->json(['message' => 'Payment not found'], 404);
        }

        DB::beginTransaction();
        try {
            if ($payment->check_receivable_id) {
                $check_receivable = CheckReceivable::findOrFail($payment->check_receivable_id);
                $check_receivable->status = CheckStatusEnum::PENDING;
                $check_receivable->save();
                CashTransaction::where('check_receivable_id', $payment->check_receivable_id)->delete();
            } else {
                $payment->delete();
            }

            $vendor = VendorModel::findOrFail($vendorId);
            $vendor->update(['debt' => $vendor->debt + abs($payment->amount)]);

            DB::commit();

            return response()->json(['message' => 'Payment deleted successfully'], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Error deleting payment', 'error' => $e->getMessage()], 500);
        }
    }


}
