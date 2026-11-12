<?php

declare(strict_types=1);

namespace App\Services\Chat;

use App\Models\Route;
use App\Models\Tenant;
use App\Models\User;

/**
 * The tech assistant's live data: who they are, what Agent Plus lets them do,
 * and how far along today's route is. Stop details (address, gate code, last
 * test, work orders) come from the my_route look-up.
 */
class AgentContext
{
    public function build(User $agent): string
    {
        $stops = Route::query()
            ->where('agent_id', $agent->id)
            ->whereDate('scheduled_date', Tenant::localToday())
            ->with('stops')
            ->first()?->stops;

        $today = $stops === null || $stops->isEmpty()
            ? 'No stops today.'
            : "Today: {$stops->count()} stops (".$stops->countBy('status')->map(fn (int $n, string $s): string => "{$n} {$s}")->join(', ').'). Use my_route for the details.';

        return "YOUR DAY\nTechnician: {$agent->displayName()}\n"
            .($agent->isAgentPlus()
                ? "Agent Plus: yes. They can rearrange their own route and skip or unskip their own stops on My Route, and see every report and work order.\n"
                : "Agent Plus: no. They can't skip, move or reassign stops themselves: the office does that, so tell them to ask the office.\n")
            .$today;
    }
}
