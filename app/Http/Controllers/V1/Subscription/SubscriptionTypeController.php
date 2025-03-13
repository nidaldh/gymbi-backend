<?php

namespace App\Http\Controllers\V1\Subscription;

use App\Http\Controllers\Controller;
use App\Models\Subscription\SubscriptionModel;
use App\Models\Subscription\SubscriptionTypeModel;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Validator;

class SubscriptionTypeController extends Controller
{

    public function index(Request $request): JsonResponse
    {
        $gymId = $request->query('gym_id');
        $query = SubscriptionTypeModel::query();

        if ($gymId) {
            $query->where('gym_id', $gymId);
        }

        $subscriptionTypes = $query->get();
        return response()->json(['subscription_types' => $subscriptionTypes]);
    }

    public function show($id): JsonResponse
    {
        $subscriptionType = SubscriptionTypeModel::findOrFail($id);
        $currentDate = now()->format('Y-m-d');

        // Count active subscriptions
        $activeSubscriptionsCount = SubscriptionModel::where('subscription_type', $id)
            ->where('end_date', '>=', $currentDate)
            ->count();

        // Count total subscriptions
        $totalSubscriptionsCount = SubscriptionModel::where('subscription_type', $id)->count();

        // Get subscriptions with filters
        $query = SubscriptionModel::where('subscription_type', $id)
            ->with('member:id,name');

        // Apply filters if they exist
        $request = request();
        if ($request->has('status')) {
            if ($request->status === 'active') {
                $query->where('end_date', '>=', $currentDate);
            } elseif ($request->status === 'expired') {
                $query->where('end_date', '<', $currentDate);
            }
        }

        if ($request->has('start_date')) {
            $query->where('start_date', '>=', $request->start_date);
        }

        if ($request->has('end_date')) {
            $query->where('end_date', '<=', $request->end_date);
        }

        // Sort by specified column
        $sortColumn = $request->input('sort_by', 'start_date');
        $sortDirection = $request->input('sort_dir', 'desc');
        $allowedColumns = ['start_date', 'end_date', 'price'];

        if (in_array($sortColumn, $allowedColumns)) {
            $query->orderBy($sortColumn, $sortDirection);
        } else {
            $query->orderBy('start_date', 'desc');
        }

        $subscriptions = $query->get();

        return response()->json([
            'subscription_type' => $subscriptionType,
            'active_subscriptions_count' => $activeSubscriptionsCount,
            'total_subscriptions_count' => $totalSubscriptionsCount,
            'subscriptions' => $subscriptions->map(function ($subscription) use ($currentDate) {
                return [
                    'id' => $subscription->member->id,
                    'member_name' => $subscription->member->name ?? 'Unknown',
                    'start_date' => $subscription->start_date,
                    'end_date' => $subscription->end_date,
                    'price' => $subscription->price,
                    'is_active' => $subscription->end_date >= $currentDate,
                    'days_remaining' => (int)($subscription->end_date >= $currentDate ?
                        now()->diffInDays(Carbon::parse($subscription->end_date)) : 0)
                ];
            })
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'price' => 'required|numeric|min:0',
            'duration' => 'required|integer|min:1',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }
        $data = $request->all();
        $data['gym_id'] = auth()->user()->gym_id;

        $subscriptionType = SubscriptionTypeModel::create($data);
        return response()->json(['data' => $subscriptionType, 'message' => 'Subscription type created successfully'],
            201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $subscriptionType = SubscriptionTypeModel::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'name' => 'string|max:255',
            'description' => 'nullable|string',
            'price' => 'numeric|min:0',
            'duration' => 'integer|min:1',
            'gym_id' => 'exists:gyms,id',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $subscriptionType->update($request->all());
        return response()->json(['data' => $subscriptionType, 'message' => 'Subscription type updated successfully']);
    }

    public function destroy(int $id): JsonResponse
    {
        $subscriptionType = SubscriptionTypeModel::findOrFail($id);
        $subscriptionType->delete();

        return response()->json(['message' => 'Subscription type deleted successfully']);
    }
}
