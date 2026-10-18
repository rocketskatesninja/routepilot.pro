<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\ServiceSubscription;
use App\Models\User;

/**
 * A tech who's removed or switched off can't sign in, so nothing on their routes
 * would ever get done. Their current plans lose their tech: each plan's upcoming
 * stops move to that day's unassigned route (through UpdateSubscription), for the
 * office to hand to someone else; the morning "Unassigned pools" alert lists them.
 */
class UnassignAgentPlans
{
    public function __construct(private readonly UpdateSubscription $plans) {}

    /** @return int how many plans were unassigned */
    public function handle(User $agent): int
    {
        $plans = ServiceSubscription::query()
            ->where('assigned_agent_id', $agent->id)
            ->whereIn('status', ['active', 'paused'])
            ->get();

        foreach ($plans as $plan) {
            $this->plans->handle($plan, ['assigned_agent_id' => null]);
        }

        return $plans->count();
    }
}
