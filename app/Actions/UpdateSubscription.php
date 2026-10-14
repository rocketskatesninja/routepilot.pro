<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\ServiceSubscription;
use App\Services\SubscriptionMaterializer;

/**
 * Change a service plan — from the plan form, the AI assistant, or anywhere else.
 * Only the given fields change. Anything that affects when or where the plan's
 * visits land re-syncs its upcoming stops; completed history is never touched.
 */
class UpdateSubscription
{
    /** Fields that can be changed. */
    private const FIELDS = [
        'service_type_id', 'assigned_agent_id', 'frequency', 'preferred_day', 'starts_on',
        'ends_on', 'day_of_month', 'status', 'hold_starts_at', 'hold_ends_at',
    ];

    /** Fields that move the plan's visits when they change. */
    private const SCHEDULE_FIELDS = [
        'assigned_agent_id', 'frequency', 'preferred_day', 'starts_on', 'ends_on',
        'day_of_month', 'status', 'hold_starts_at', 'hold_ends_at',
    ];

    public function __construct(private readonly SubscriptionMaterializer $scheduler) {}

    /**
     * @param  array<string, mixed>  $changes
     */
    public function handle(ServiceSubscription $subscription, array $changes): ServiceSubscription
    {
        $subscription->update(array_intersect_key($changes, array_flip(self::FIELDS)));

        if ($subscription->wasChanged(self::SCHEDULE_FIELDS)) {
            $this->scheduler->resync($subscription);
        }

        return $subscription;
    }
}
