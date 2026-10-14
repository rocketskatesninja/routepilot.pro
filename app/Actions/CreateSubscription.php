<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\ServiceSubscription;
use App\Models\Tenant;
use App\Services\SubscriptionMaterializer;

/**
 * Attach a service plan to a pool and put its visits on the routes right away.
 */
class CreateSubscription
{
    public function __construct(private readonly SubscriptionMaterializer $scheduler) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(array $data): ServiceSubscription
    {
        $plan = ServiceSubscription::create([
            'pool_id' => $data['pool_id'],
            'service_type_id' => $data['service_type_id'],
            'assigned_agent_id' => $data['assigned_agent_id'] ?? null,
            'frequency' => $data['frequency'],
            'preferred_day' => $data['preferred_day'] ?? null,
            'starts_on' => $data['starts_on'] ?? Tenant::localToday()->toDateString(),
            'ends_on' => $data['ends_on'] ?? null,
            'day_of_month' => $data['day_of_month'] ?? null,
            'status' => 'active',
        ]);

        $this->scheduler->resync($plan);

        return $plan;
    }
}
