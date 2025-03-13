<?php

namespace App\Http\Controllers\V1;

use App\Enums\CheckStatusEnum;
use App\Http\Controllers\Controller;
use App\Http\Requests\Member\MemberRequest;
use App\Models\CashTransaction;
use App\Models\CheckReceivable;
use App\Models\MemberModel;
use App\Models\Order\OrderModel;
use App\Models\Subscription\SubscriptionModel;
use App\Models\Subscription\SubscriptionTypeModel;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Maatwebsite\Excel\Excel;

class MemberController extends Controller
{
    protected $excel;

    public function __construct(Excel $excel)
    {
        $this->excel = $excel;
    }

    public function addMember(MemberRequest $request)
    {
        $member = new MemberModel([
            'name' => $request->name,
            'mobile' => $request->mobile,
            'gym_id' => auth()->user()->gym_id,
            'date_of_birth' => $request->date_of_birth,
            'gender' => $request->gender,
            'debt' => 0,
        ]);

        $member->save();

        return response()->json(['message' => 'Member added successfully', 'member' => $member], 200);
    }

    public function updateMember(MemberRequest $request, $id)
    {
        $member = MemberModel::findOrFail($id);
        $member->update($request->all());

        return response()->json(['message' => 'Member updated successfully', 'member' => $member], 200);
    }

    public function getMemberById($id)
    {
        $member = MemberModel::findOrFail($id);

        $activeSubscriptions = SubscriptionModel::where('member_id', $id)
//            ->where('start_date', '<=', now()->format('Y-m-d'))
            ->where('end_date', '>=', now()->format('Y-m-d'))
            ->with('subscriptionType')
            ->get();

        $activeSubscriptions = $activeSubscriptions->map(function ($subscription) {
            return [
                'id' => $subscription->id,
                'name' => $subscription->subscriptionType->name,
                'start_date' => $subscription->start_date,
                'end_date' => $subscription->end_date,
                'price' => $subscription->price,
            ];
        });

        return response()->json([
            'member' => $member,
            'active_subscriptions' => $activeSubscriptions
        ], 200);
    }

    public function index(Request $request)
    {
        $gymId = auth()->user()->gym_id;
        $query = MemberModel::where('gym_id', $gymId);

        // Filter by active subscription status if requested
        if ($request->has('active')) {
            $active = filter_var($request->active, FILTER_VALIDATE_BOOLEAN);
            if ($active) {
                $query->whereHas('subscriptions', function ($q) {
                    $q->where('end_date', '>=', now()->format('Y-m-d'));
                });
            } else {
                $query->whereDoesntHave('subscriptions', function ($q) {
                    $q->where('end_date', '>=', now()->format('Y-m-d'));
                });
            }
        }

        // Filter by search term
        if ($request->has('search')) {
            $searchTerm = $request->search;
            $query->where(function ($q) use ($searchTerm) {
                $q->where('name', 'like', "%{$searchTerm}%")
                    ->orWhere('mobile', 'like', "%{$searchTerm}%");
            });
        }

        // Sort by specified column
        $sortColumn = $request->input('sort_by', 'updated_at');
        $sortDirection = $request->input('sort_dir', 'desc');
        $allowedColumns = ['name', 'created_at', 'updated_at', 'debt'];

        if (in_array($sortColumn, $allowedColumns)) {
            $query->orderBy($sortColumn, $sortDirection);
        } else {
            $query->orderBy('updated_at', 'desc');
        }

        $members = $query->with([
            'subscriptions' => function ($query) {
                $query->where('end_date', '>=', now()->format('Y-m-d'))
                    ->with('subscriptionType:id,name');
            }
        ])->get();

        $members = $members->map(function ($member) {
            $activeSubscriptions = $member->subscriptions->filter(function ($subscription) {
                return $subscription->end_date >= now()->format('Y-m-d');
            });

            return [
                'id' => $member->id,
                'name' => $member->name,
                'gender' => $member->gender,
                'debt' => (float)$member->debt,
                'has_active_subscription' => $activeSubscriptions->count() > 0,
            ];
        });

        return response()->json(['members' => $members], 200);
    }

    public function deleteMember($id)
    {
        $member = MemberModel::findOrFail($id);
        $member->delete();

        return response()->json(['message' => 'Member deleted successfully'], 200);
    }

    public function getMemberOrders($id)
    {
        $member = MemberModel::findOrFail($id);
        $orders = OrderModel::where('member_id', $id)
            ->orderBy('updated_at', 'desc')
//            ->with('orderProducts', 'orderTransactions')
            ->get();

        return response()->json(['member' => $member, 'orders' => $orders], 200);
    }

    public function addSubscription(Request $request, $memberId)
    {
        $gymId = auth()->user()->gym_id;
        $member = MemberModel::where('gym_id', $gymId)->findOrFail($memberId);

        $validator = Validator::make($request->all(), [
            'subscription_type_id' => 'required|exists:subscription_types,id',
            'start_date' => 'required|date',
            'price' => 'required|numeric|min:0',
            'paymentDetails' => 'nullable|array',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $subscriptionType = SubscriptionTypeModel::findOrFail($request->subscription_type_id);

        // Check if subscription type belongs to the same gym
        if ($subscriptionType->gym_id != $gymId) {
            return response()->json(['message' => 'Subscription type does not belong to this gym'], 403);
        }

        DB::beginTransaction();

        try {
            $end_date = date('Y-m-d', strtotime($request->start_date . ' + ' . $subscriptionType->duration . ' days'));
            //if the member has an active subscription, in the same period, then change the end date of the active subscription to the start date of the new subscription
            $activeSubscription = SubscriptionModel::where('member_id', $memberId)
                ->where('start_date', '<=', $request->start_date)
                ->where('end_date', '>=', $request->start_date)
                ->first();
            if ($activeSubscription) {
                $request->start_date = $activeSubscription->end_date;
                $end_date = date(
                    'Y-m-d',
                    strtotime($request->start_date . ' + ' . $subscriptionType->duration . ' days')
                );
            }

            $subscription = new SubscriptionModel([
                'member_id' => $memberId,
                'subscription_type' => $request->subscription_type_id,
                'start_date' => $request->start_date,
                'end_date' => $end_date,
                'price' => $request->price ?? $subscriptionType,
                'gym_id' => $gymId,
            ]);

            $subscription->save();

            // Update member's debt and handle payment
            $member->debt += $request->price;
            $paid_amount = 0;

            if (!empty($request->paymentDetails)) {
                if (!empty($request->paymentDetails['cash'])) {
                    $cash_data = [
                        'amount' => $request->paymentDetails['cash'],
                        'gym_id' => $gymId,
                        'customer_id' => $memberId,
                        'notes' => 'Subscription payment'
                    ];

                    $cashTransaction = new CashTransaction($cash_data);
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
                            'customer_id' => $memberId,
                            'gym_id' => $gymId,
                        ]);
                        $paid_amount += $check['amount'];
                        $check_receivable->save();
                    }
                }

                // Deduct paid amount from member's debt
                $member->debt -= $paid_amount;

                // If there's remaining amount, allocate to old orders
                if ($paid_amount > $request->price) {
                    $remaining_amount = $paid_amount - $request->price;
                    $this->allocateRemainingAmountToOldOrders($memberId, $remaining_amount);
                }
            }

            $member->save();

            DB::commit();

            return response()->json([
                'message' => 'Subscription added successfully',
                'subscription' => $subscription
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Error adding subscription: ' . $e->getMessage()], 500);
        }
    }

    private function allocateRemainingAmountToOldOrders($member_id, $remaining_amount): void
    {
        $old_orders = OrderModel::where('member_id', $member_id)
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

    public function deleteSubscription($memberId, $subscriptionId)
    {
        $gymId = auth()->user()->gym_id;
        $member = MemberModel::where('gym_id', $gymId)->findOrFail($memberId);

        $subscription = SubscriptionModel::where('member_id', $memberId)
            ->findOrFail($subscriptionId);

        // Check if we should update the debt
        $price = $subscription->price;

        $subscription->delete();

        return response()->json(['message' => 'Subscription deleted successfully'], 200);
    }

    public function getMemberSubscriptions($memberId)
    {
        $gymId = auth()->user()->gym_id;
        $member = MemberModel::where('gym_id', $gymId)->findOrFail($memberId);

        $subscriptions = SubscriptionModel::where('member_id', $memberId)
            ->with('subscriptionType')
            ->orderBy('start_date', 'desc')
            ->get();

        return response()->json([
            'member' => $member,
            'subscriptions' => $subscriptions
        ], 200);
    }


}
