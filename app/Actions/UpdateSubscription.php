<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\ServiceSubscription;

/**
 * Update a subscription's plan/cadence/agent/status, including pause
 * (status) and dated vacation holds.
 */
class UpdateSubscription
{
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

        return $subscription;
    }
}
