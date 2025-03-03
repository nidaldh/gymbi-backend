<?php

namespace App\Http\Controllers;

use App\Http\Requests\Expense\ExpenseRequest;
use App\Http\Requests\Expense\ExpenseTransactionRequest;
use App\Models\Expense\ExpenseModel;
use App\Models\Expense\ExpenseTransactionModel;
use Carbon\Carbon;
use Illuminate\Http\Request;

class ExpenseController extends Controller
{
    public function index(Request $request)
    {
        $query = ExpenseModel::with('transactions')->where('store_id', auth()->user()->store_id);
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

        $expenses = $query->get();
        return response()->json(['expenses' => $expenses]);
    }

    public function getExpensesCategories()
    {
        $categories = ExpenseModel::where('store_id', auth()->user()->store_id)
            ->distinct('category')->pluck('category');
        return response()->json(['categories' => $categories]);
    }

    public function show($id)
    {
        $expense = ExpenseModel::with('transactions')
            ->where('store_id', auth()->user()->store_id)
            ->findOrFail($id);
        return response()->json($expense);
    }

    public function store(ExpenseRequest $request)
    {
        $storeId = auth()->user()->store_id;
        $request->merge(['store_id' => $storeId]);
        $expense = ExpenseModel::create($request->all());

        if ($request->has('paid_amount') && $request->paid_amount > 0) {
            $transaction = new ExpenseTransactionModel([
                'amount' => $request->paid_amount,
                'date' => Carbon::now(),
                'description' => 'Initial payment',
                'expense_id' => $expense->id,
            ]);
            $transaction->save();

            $expense->update(
                ['paid_amount' => $request->paid_amount, 'unpaid_amount' => $expense->total - $request->paid_amount]
            );
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
        $expense->cashTransactions()->delete();
        $expense->transactions()->delete();
        $expense->delete();
        return response()->json(null, 200);
    }

    public function addTransaction(ExpenseTransactionRequest $request, $expenseId)
    {
        //todo: check if expense belongs to the store
        $expense = ExpenseModel::findOrFail($expenseId);

        if ($request->amount > $expense->unpaid_amount) {
            return response()->json(['error' => 'Transaction amount exceeds unpaid amount'], 400);
        }

        $transaction = new ExpenseTransactionModel($request->validated());
        $transaction->expense_id = $expenseId;

        $expense->update(
            [
                'paid_amount' => $expense->paid_amount + $request->amount,
                'unpaid_amount' => $expense->unpaid_amount - $request->amount
            ]
        );
        $transaction->save();

        return response()->json($transaction, 201);
    }

    public function getTransactions($expenseId)
    {
        $transactions = ExpenseTransactionModel::where('expense_id', $expenseId)->get();
        return response()->json($transactions);
    }
}
