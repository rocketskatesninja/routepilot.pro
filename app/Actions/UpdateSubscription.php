<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\RouteStop;
use App\Models\ServiceSubscription;
use App\Services\SubscriptionMaterializer;

/**
 * Update a subscription's plan/cadence/agent/status, including pause
 * (status) and dated vacation holds. Reassigning the agent moves the
 * plan's upcoming (pending) stops to the new tech.
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

        // When the tech changes, hand the upcoming work to them: drop this plan's
        // future pending stops (completed history stays) and re-materialize, which
        // recreates them on the newly-assigned agent's routes.
        if ($subscription->wasChanged('assigned_agent_id')) {
            RouteStop::query()
                ->where('service_subscription_id', $subscription->id)
                ->where('status', 'pending')
                ->whereHas('route', fn ($q) => $q->whereDate('scheduled_date', '>=', now()->toDateString()))
                ->delete();

            $this->materializer->run((int) $subscription->getAttribute('tenant_id'));
        }

        return $subscription;
    }
}
