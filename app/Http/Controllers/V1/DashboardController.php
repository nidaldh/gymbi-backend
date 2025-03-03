<?php


namespace App\Http\Controllers\V1;

use App\Enums\CheckStatusEnum;
use App\Http\Controllers\Controller;
use App\Models\CashTransaction;
use App\Models\CheckPayable;
use App\Models\CheckReceivable;
use App\Models\CustomerModel;
use App\Models\Expense\ExpenseModel;
use App\Models\VendorModel;
use App\Services\DashboardService;
use Illuminate\Http\Request;

class DashboardController extends Controller
{

    public function index()
    {
        $storeId = auth()->user()->store_id;
        $total_cash = CashTransaction::where('store_id', $storeId)->sum('amount');
        $total_customer_debt = CustomerModel::where('store_id', $storeId)->sum('debt');
        $total_vendor_debt = VendorModel::where('store_id', $storeId)
            ->where('debt', '>', 0)
            ->sum('debt');
        $total_payable_checks = CheckPayable::where('store_id', $storeId)
            ->where('status', CheckStatusEnum::PENDING)->sum('amount');
        $total_receivable_checks = CheckReceivable::where('store_id', $storeId)
            ->where('status', CheckStatusEnum::PENDING)->sum('amount');
        $total_unpaid_expenses = ExpenseModel::where('store_id', $storeId)
            ->sum('unpaid_amount');

        return response()->json([
            'total_cash' => (double)$total_cash,
            'total_customer_debt' => (double)$total_customer_debt,
            'total_vendor_debt' => (double)$total_vendor_debt,
            'total_payable_checks' => (double)$total_payable_checks,
            'total_receivable_checks' => (double)$total_receivable_checks,
            'total_unpaid_expenses' => (double)$total_unpaid_expenses
        ]);
    }

    public function getMonthlyExpenses(Request $request)
    {
        $storeId = auth()->user()->store_id;
        $dashboard = new DashboardService($storeId);
        $monthlyExpenses = $dashboard->getMonthlyExpenses();
        return response()->json(['monthly_expenses' => $monthlyExpenses]);
    }

    public function getMonthlySales(Request $request)
    {
        $storeId = auth()->user()->store_id;
        $dashboard = new DashboardService($storeId);
        $monthlySales = $dashboard->getMonthlySales();
        return response()->json(['monthly_sales' => $monthlySales]);
    }

    public function getMonthlyPurchases(Request $request)
    {
        $storeId = auth()->user()->store_id;
        $dashboard = new DashboardService($storeId);
        $monthlyPurchases = $dashboard->getMonthlyPurchases();
        return response()->json(['monthly_purchases' => $monthlyPurchases]);
    }

    public function getTotalProducts(Request $request)
    {
        $storeId = auth()->user()->store_id;
        $dashboard = new DashboardService($storeId);
        $totalProducts = $dashboard->getTotalProducts();
        return response()->json(['total_products' => $totalProducts]);
    }

    public function getTotalVendors(Request $request)
    {
        $storeId = auth()->user()->store_id;
        $dashboard = new DashboardService($storeId);
        $totalVendors = $dashboard->getTotalVendors();
        return response()->json(['total_vendors' => $totalVendors]);
    }
}
