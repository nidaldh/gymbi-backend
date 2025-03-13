<?php

namespace App\Http\Controllers\V1;

use App\Enums\CheckStatusEnum;
use App\Http\Controllers\Controller;
use App\Http\Requests\BaseRequest;
use App\Http\Requests\Expense\ExpenseRequest;
use App\Models\CashTransaction;
use App\Models\CheckPayable;
use App\Models\CheckReceivable;
use App\Models\Expense\ExpenseModel;
use App\Models\VendorModel;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ExpenseController extends Controller
{
    public function index(Request $request)
    {
        $query = ExpenseModel::with('transactions')->where('gym_id', auth()->user()->gym_id);
        $startOfMonth = Carbon::now()->startOfMonth()->toDateString();
        $endOfMonth = Carbon::now()->endOfMonth()->toDateString();
        $start_date = $request->start_date ?? $startOfMonth;
        $end_date = $request->end_date ?? $endOfMonth;
        $query->whereBetween('date', [$start_date, $end_date]);
        if ($request->has('category')) {
            $query->where('category', $request->category);
        }

        if ($request->has('paid')) {
            if ($request->paid == 'true') {
                $query->whereColumn('total', '=', 'paid_amount');
            } else {
                $query->whereColumn('total', '>', 'paid_amount');
            }
        }

        $expenses = $query
            ->orderBy('date', 'desc')
            ->get();
        $total = $expenses->sum('total');
        $unpaid = $expenses->sum('unpaid_amount');
        return response()->json(['expenses' => $expenses, 'total' => $total, 'unpaid' => $unpaid]);
    }

    public function getExpensesCategories()
    {
        $categories = ExpenseModel::where('gym_id', auth()->user()->gym_id)
            ->distinct('category')->pluck('category');
        return response()->json(['categories' => $categories]);
    }

    public function show($id)
    {
        $expense = ExpenseModel::where('gym_id', auth()->user()->gym_id)
            ->findOrFail($id);

        $gym_id = auth()->user()->gym_id;
        $payments = CashTransaction::where('expense_id', $id)
            ->where('gym_id', $gym_id)
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
                    'id' => $transaction->checkReceivable->id,
                    'check_number' => $transaction->checkReceivable->check_number,
                    'issuer_name' => $transaction->checkReceivable->issuer_name,
                    'amount' => abs($transaction->checkReceivable->amount),
                    'due_date' => $transaction->checkReceivable->due_date,
                    'status' => $transaction->checkReceivable->status,
                    'bank' => $transaction->checkReceivable->bank->name_ar,
                    'type' => 'check_receivable',
                ];
            });

        $checkPayments = CheckPayable::where('expense_id', $id)
            ->where('gym_id', $gym_id)
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
        $expense->payments = $payments;


        return response()->json($expense);
    }

    public function store(ExpenseRequest $request)
    {
        $gymId = auth()->user()->gym_id;
        $request->merge(['gym_id' => $gymId]);
        $expense = ExpenseModel::create($request->all());

        if (!empty($request->paymentDetails)) {
            $this->addPayment($request, $expense->id, true);
        } else {
            $expense->update(['unpaid_amount' => $expense->total]);
        }

        return response()->json($expense, 201);
    }

    public function update(ExpenseRequest $request, $id)
    {
        $expense = ExpenseModel::findOrFail($id);
        $expense->update($request->all());
        return response()->json($expense);
    }

    public function destroy($id)
    {
        $expense = ExpenseModel::findOrFail($id);
        $transactions = $expense->transactions;
        foreach ($transactions as $transaction) {
            $transaction->delete();
        }
        $expense->cashTransactions()->delete();
        $expense->checkPayable()->delete();

        $expense->delete();
        return response()->json(null, 200);
    }

    public function addPayment(BaseRequest $request, $expenseId, $firstPayment = false)
    {
        $request->validate([
            'paymentDetails.cash' => 'nullable|numeric',
            'paymentDetails.receivable_check_ids' => 'nullable|array',
            'paymentDetails.receivable_check_ids.*' => 'required_with:paymentDetails.receivable_check_ids|integer|exists:check_receivable,id',
            'paymentDetails.payable_checks' => 'nullable|array',
            'paymentDetails.payable_checks.*.check_number' => 'required_with:paymentDetails.payable_checks|string',
            'paymentDetails.payable_checks.*.bank_id' => 'required_with:paymentDetails.payable_checks|integer|exists:banks,id',
            'paymentDetails.payable_checks.*.amount' => 'required_with:paymentDetails.payable_checks|numeric',
            'paymentDetails.payable_checks.*.due_date' => 'required_with:paymentDetails.payable_checks|date',
        ]);

        DB::beginTransaction();

        try {
            $paid_amount = 0;
            $gym_id = auth()->user()->gym_id;
            $expense = ExpenseModel::findOrFail($expenseId);

            if (!empty($request->paymentDetails['cash'])) {
                $transaction = new CashTransaction([
                    'expense_id' => $expenseId,
                    'amount' => -$request->paymentDetails['cash'],
                    'gym_id' => $gym_id,
                    'notes' => 'Cash payment',
                ]);
                $paid_amount += $request->paymentDetails['cash'];
                $transaction->save();
            }

            if (!empty($request->paymentDetails['receivable_check_ids'])) {
                foreach ($request->paymentDetails['receivable_check_ids'] as $checkId) {
                    $check_receivable = CheckReceivable::findOrFail($checkId);

                    $cashTransaction = new CashTransaction([
                        'check_receivable_id' => $check_receivable->id,
                        'amount' => $check_receivable->amount,
                        'gym_id' => $gym_id,
                    ]);
                    $cashTransaction->save();

                    $cashTransaction = new CashTransaction([
                        'expense_id' => $expenseId,
                        'amount' => -$check_receivable->amount,
                        'gym_id' => $gym_id,
                        'check_receivable_id' => $check_receivable->id,
                    ]);

                    $cashTransaction->save();

                    $check_receivable->status = CheckStatusEnum::CLEARED;
                    $check_receivable->save();
                    $paid_amount += $check_receivable->amount;
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
                        'gym_id' => $gym_id,
                        'expense_id' => $expenseId,
                    ]);
                    $check_payable->save();
                    $paid_amount += $check['amount'];
                }
            }

            if (!$firstPayment) {
                $expense->update(
                    [
                        'unpaid_amount' => $expense->unpaid_amount - $paid_amount,
                        'paid_amount' => $expense->paid_amount + $paid_amount
                    ]
                );
            } else {
                $expense->update(
                    [
                        'unpaid_amount' => $expense->total - $paid_amount,
                        'paid_amount' => $paid_amount
                    ]
                );
            }

            DB::commit();

            return response()->json(['message' => 'Payment added successfully', 'paid_amount' => $paid_amount], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Error adding payment', 'error' => $e->getMessage()], 500);
        }
    }

    public function deletePayment($expenseId, $paymentId, Request $request)
    {
        $gym_id = auth()->user()->gym_id;

        if ($request->type == 'check_payable') {
            $payment = CheckPayable::where('expense_id', $expenseId)
                ->where('gym_id', $gym_id)
                ->where('id', $paymentId)
                ->first();
        } else {
            $payment = CashTransaction::where('expense_id', $expenseId)
                ->where('gym_id', $gym_id)
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
                CashTransaction::where('check_receivable_id', $payment->check_receivable_id)
                    ->where('expense_id', $expenseId)
                    ->delete();
            } else {
                $payment->delete();
            }
            $expense = ExpenseModel::findOrFail($expenseId);
            $amount = abs($payment->amount);
            $expense->update(
                [
                    'unpaid_amount' => $expense->unpaid_amount + $amount,
                    'paid_amount' => $expense->paid_amount - $amount,
                ]
            );
            DB::commit();
            return response()->json(['message' => 'Payment deleted successfully'], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Error deleting payment', 'error' => $e->getMessage()], 500);
        }
    }
}
