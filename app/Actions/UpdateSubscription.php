<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\ServiceSubscription;
use App\Services\SubscriptionMaterializer;

/**
 * Update a subscription's plan/cadence/agent/status, including pause
 * (status) and dated vacation holds. Any change to where or when the plan
 * lands re-syncs its upcoming (pending) stops onto the new schedule.
 */
class UpdateSubscription
{
    public function __construct(private readonly SubscriptionMaterializer $materializer) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(ServiceSubscription $subscription, array $data): ServiceSubscription
    {
        $subscription->update([
            'service_type_id' => $data['service_type_id'],
            'assigned_agent_id' => $data['assigned_agent_id'] ?? null,
            'frequency' => $data['frequency'],
            'preferred_day' => $data['preferred_day'] ?? null,
            'status' => $data['status'],
            'hold_starts_at' => $data['hold_starts_at'] ?? null,
            'hold_ends_at' => $data['hold_ends_at'] ?? null,
        ]);

        // Anything that changes where or when the plan lands (tech, day, cadence,
        // status, vacation hold) re-syncs its upcoming stops; completed history
        // is never touched.
        if ($subscription->wasChanged(['assigned_agent_id', 'preferred_day', 'frequency', 'status', 'hold_starts_at', 'hold_ends_at'])) {
            $this->materializer->resync($subscription);
        }

        return $subscription;
    }
}
