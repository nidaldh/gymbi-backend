<?php

namespace App\Http\Controllers\V1;

use App\Http\Controllers\Controller;
use App\Models\CashTransaction;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class CashController extends Controller
{
    public function index(Request $request)
    {
        $gym_id = auth()->user()->gym_id;
        $income = 0;
        $outcome = 0;
        $year = $request->year ?? Carbon::now()->year;
        $month = $request->month ?? Carbon::now()->month;
        $transactions = CashTransaction::where('gym_id', $gym_id)
            ->with(['expense', 'vendor', 'customer', 'order'])
            ->whereYear('created_at', $year)
            ->whereMonth('created_at', $month)
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($transaction) use (&$income, &$outcome) {
                if ($transaction->amount > 0) {
                    $income += $transaction->amount;
                } else {
                    $outcome += $transaction->amount;
                }
                $data = [
                    'id' => $transaction->id,
                    'amount' => $transaction->amount,
                    'created_at' => $transaction->created_at,
                    'expense_category' => $transaction->expense ? $transaction->expense->category : null,
                    'vendor_name' => $transaction->vendor ? $transaction->vendor->name : null,
                    'customer_name' => $transaction->customer ? $transaction->customer->name : null,
                    'order_id' => $transaction->order ? $transaction->order->id : null,
                ];
                if ($transaction->checkReceivable) {
                    $data['receivable_check'] = $transaction->checkReceivable->check_number . ' من ' . $transaction->checkReceivable->issuer_name;
                }
                if ($transaction->checkPayable) {
                    $data['payable_check'] = $transaction->checkPayable->check_number;
                }
                return $data;
            });

        $filtered_cash_amount = $transactions->sum('amount');

        return response()->json(
            [
                'transactions' => $transactions,
                'total_cash' => $filtered_cash_amount,
                'income' => $income,
                'outcome' => $outcome,
            ]
        );
    }

    public function spentCashOnExpenses()
    {
        $gym_id = auth()->user()->gym_id;
        $transactions = CashTransaction::where('gym_id', $gym_id)
            ->where('expense_id', '!=', null)
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json(['transactions' => $transactions]);
    }

    public function spentCashOnPurchases()
    {
        $gym_id = auth()->user()->gym_id;
        $transactions = CashTransaction::where('gym_id', $gym_id)
            ->where('vendor_id', '!=', null)
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json(['transactions' => $transactions]);
    }

    public function cashFromSales()
    {
        $gym_id = auth()->user()->gym_id;
        $transactions = CashTransaction::where('gym_id', $gym_id)
            ->where('sale_id', '!=', null)
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json(['transactions' => $transactions]);
    }
}
