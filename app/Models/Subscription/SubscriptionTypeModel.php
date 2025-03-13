<?php

namespace App\Models\Subscription;

use App\Models\GymModel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SubscriptionTypeModel extends Model
{
    use HasFactory;

    protected $table = 'subscription_types';
    protected $fillable = [
        'name',
        'description',
        'price',
        'duration',
        'gym_id',
    ];

    protected $casts = [
        'price' => 'double',
        'duration' => 'integer',
    ];

    /**
     * Get the gym that owns the subscription type.
     */
    public function gym(): BelongsTo
    {
        return $this->belongsTo(GymModel::class);
    }
}
