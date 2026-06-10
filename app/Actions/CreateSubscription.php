<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\ServiceSubscription;

/**
 * Attach a recurring service plan to a pool. The materializer turns active
 * subscriptions into route stops.
 */
class CreateSubscription
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(array $data): ServiceSubscription
    {
        return ServiceSubscription::create([
            'pool_id' => $data['pool_id'],
            'service_type_id' => $data['service_type_id'],
            'assigned_agent_id' => $data['assigned_agent_id'] ?? null,
            'frequency' => $data['frequency'],
            'preferred_day' => $data['preferred_day'] ?? null,
            'status' => 'active',
        ]);
    }
}
