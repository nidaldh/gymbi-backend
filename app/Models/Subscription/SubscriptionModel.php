<?php

namespace App\Models\Subscription;

use App\Models\GymModel;
use App\Models\MemberModel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SubscriptionModel extends Model
{
    use HasFactory;

    protected $table = 'subscriptions';

    protected $fillable = [
        'member_id',
        'subscription_type',
        'start_date',
        'end_date',
        'price',
        'gym_id',
    ];

    protected $casts = [
        'price' => 'double',
    ];

    /**
     * Get the member that owns the subscription.
     */
    public function member(): BelongsTo
    {
        return $this->belongsTo(MemberModel::class, 'member_id');
    }

    /**
     * Get the subscription type that belongs to the subscription.
     */
    public function subscriptionType(): BelongsTo
    {
        return $this->belongsTo(SubscriptionTypeModel::class, 'subscription_type');
    }


    public function gym(): BelongsTo
    {
        return $this->belongsTo(GymModel::class, 'gym_id');
    }
}
