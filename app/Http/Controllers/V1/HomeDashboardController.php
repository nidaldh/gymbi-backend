<?php

namespace App\Http\Controllers\V1;

use App\Http\Controllers\Controller;
use App\Models\Expense\ExpenseModel;
use App\Models\MemberModel;
use App\Models\Order\OrderModel;
use App\Models\PurchaseModel;
use App\Models\Subscription\SubscriptionModel;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class HomeDashboardController extends Controller
{
    /**
     * Get dashboard statistics
     *
     * @return JsonResponse
     */
    public function getDashboardStats(): JsonResponse
    {
        $gymId = auth()->user()->gym_id;
        $now = Carbon::now();
        $startOfMonth = $now->copy()->startOfMonth()->format('Y-m-d');
        $endOfMonth = $now->copy()->endOfMonth()->format('Y-m-d');
        $currentDate = $now->format('Y-m-d');

        // Active members (with valid subscriptions)
        $activeMembersCount = MemberModel::whereHas('subscriptions', function ($query) use ($currentDate) {
            $query->where('end_date', '>=', $currentDate);
        })->where('gym_id', $gymId)->count();

        // Active members per subscription type
        $activeMembersBySubscription = DB::table('subscription_types')
            ->leftJoin('subscriptions', 'subscription_types.id', '=', 'subscriptions.subscription_type')
            ->where('subscription_types.gym_id', $gymId)
            ->where('subscriptions.end_date', '>=', $currentDate)
            ->select(
                'subscription_types.id',
                'subscription_types.name',
                DB::raw('COUNT(subscriptions.id) as active_members')
            )
            ->groupBy('subscription_types.id', 'subscription_types.name')
            ->orderBy('active_members', 'desc')
            ->get();

        // Total members
        $totalMembers = MemberModel::where('gym_id', $gymId)->count();

        // Total member debt
        $totalDebt = MemberModel::where('gym_id', $gymId)->sum('debt');

        // Sales amount this month
        $monthlySales = OrderModel::where('gym_id', $gymId)
            ->whereBetween('created_at', [$startOfMonth, $endOfMonth])
            ->sum('totalPrice');

        // Most sold product this month
        $mostSoldProduct = DB::table('order_products')
            ->join('orders', 'order_products.order_id', '=', 'orders.id')
            ->select('order_products.name', DB::raw('SUM(order_products.quantity) as quantity'))
            ->where('orders.gym_id', $gymId)
            ->whereBetween('orders.created_at', [$startOfMonth, $endOfMonth])
            ->groupBy('order_products.productId', 'order_products.name')
            ->orderBy('quantity', 'desc')
            ->limit(3)->get();

        $monthlyExpenses = ExpenseModel::where('gym_id', $gymId)
            ->whereBetween('created_at', [$startOfMonth, $endOfMonth])
            ->sum('total');

        $monthlyPurchases = PurchaseModel::where('gym_id', $gymId)
            ->whereBetween('created_at', [$startOfMonth, $endOfMonth])
            ->sum('total');

        $soldSubscriptions = SubscriptionModel::where('gym_id', $gymId)
            ->whereBetween('created_at', [$startOfMonth, $endOfMonth])
            ->count();

        $soldSubscriptionsAmount = SubscriptionModel::where('gym_id', $gymId)
            ->whereBetween('created_at', [$startOfMonth, $endOfMonth])
            ->sum('price');


        return response()->json([
            'active_members_count' => $activeMembersCount,
            'inactive_members_count' => $totalMembers - $activeMembersCount,
            'active_members_by_subscription' => $activeMembersBySubscription,
            'total_members' => $totalMembers,
            'total_debt' => (double)$totalDebt,
            'monthly_sales' => (double)$monthlySales,
            'monthly_expenses' => (double)$monthlyExpenses,
            'monthly_purchases' => (double)$monthlyPurchases,
            'sold_subscriptions' => $soldSubscriptions,
            'sold_subscriptions_amount' => (double)$soldSubscriptionsAmount,
            'most_sold_product' => $mostSoldProduct
        ]);
    }
}
