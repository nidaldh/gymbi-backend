<?php


namespace App\Http\Controllers\V1;

use App\Enums\CheckStatusEnum;
use App\Http\Controllers\Controller;
use App\Models\CashTransaction;
use App\Models\CheckPayable;
use App\Models\CheckReceivable;
use App\Models\MemberModel;
use App\Models\Expense\ExpenseModel;
use App\Models\VendorModel;
use App\Services\DashboardService;
use Illuminate\Http\Request;

class DashboardController extends Controller
{

    public function index()
    {
        $gymId = auth()->user()->gym_id;
        $total_cash = CashTransaction::where('gym_id', $gymId)->sum('amount');
        $total_customer_debt = MemberModel::where('gym_id', $gymId)->sum('debt');
        $total_vendor_debt = VendorModel::where('gym_id', $gymId)
            ->where('debt', '>', 0)
            ->sum('debt');
        $total_payable_checks = CheckPayable::where('gym_id', $gymId)
            ->where('status', CheckStatusEnum::PENDING)->sum('amount');
        $total_receivable_checks = CheckReceivable::where('gym_id', $gymId)
            ->where('status', CheckStatusEnum::PENDING)->sum('amount');
        $total_unpaid_expenses = ExpenseModel::where('gym_id', $gymId)
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
        $gymId = auth()->user()->gym_id;
        $dashboard = new DashboardService($gymId);
        $monthlyExpenses = $dashboard->getMonthlyExpenses();
        return response()->json(['monthly_expenses' => $monthlyExpenses]);
    }

    public function getMonthlySales(Request $request)
    {
        $gymId = auth()->user()->gym_id;
        $dashboard = new DashboardService($gymId);
        $monthlySales = $dashboard->getMonthlySales();
        return response()->json(['monthly_sales' => $monthlySales]);
    }

    public function getMonthlyPurchases(Request $request)
    {
        $gymId = auth()->user()->gym_id;
        $dashboard = new DashboardService($gymId);
        $monthlyPurchases = $dashboard->getMonthlyPurchases();
        return response()->json(['monthly_purchases' => $monthlyPurchases]);
    }

    public function getTotalProducts(Request $request)
    {
        $gymId = auth()->user()->gym_id;
        $dashboard = new DashboardService($gymId);
        $totalProducts = $dashboard->getTotalProducts();
        return response()->json(['total_products' => $totalProducts]);
    }

    public function getTotalVendors(Request $request)
    {
        $gymId = auth()->user()->gym_id;
        $dashboard = new DashboardService($gymId);
        $totalVendors = $dashboard->getTotalVendors();
        return response()->json(['total_vendors' => $totalVendors]);
    }
}
