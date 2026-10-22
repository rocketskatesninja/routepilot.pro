<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Lead;
use App\Models\ServiceRequest;
use App\Models\ServiceVisit;
use App\Models\User;
use App\Models\WorkOrder;
use App\Policies\WorkOrderPolicy;

/**
 * The numbers on the sidebar's links, for the company's staff:
 *  - Requests (admins): customer requests waiting for an answer + new website leads
 *  - Work orders: open ones assigned to you; admins and Agent+ also see the unassigned
 *  - Reports (admins): reports filed since you last opened Reports
 * Each drops as the work gets done (or, for Reports, when you look).
 */
final class NavCounts
{
    /** @return array{requests?: int, work_orders?: int, reports?: int} */
    public static function for(?User $user): array
    {
        if ($user === null || $user->tenant_id === null || ! in_array($user->role, ['tenant_admin', 'agent'], true)) {
            return [];
        }
        $counts = [
            'work_orders' => WorkOrder::query()->open()
                ->where(fn ($q) => $q->where('assigned_to', $user->id)->when(WorkOrderPolicy::runsTheList($user), fn ($q) => $q->orWhereNull('assigned_to')))
                ->count(),
        ];
        if ($user->role === 'tenant_admin') {
            $counts['requests'] = ServiceRequest::query()->where('status', 'pending')->whereHas('customer')->count()
                + Lead::query()->where('status', 'new')->count();
            $counts['reports'] = ServiceVisit::query()->where('status', 'completed')
                ->where('completed_at', '>', $user->getAttribute('reports_seen_at') ?? $user->created_at)->count();
        }

        return $counts;
    }

    /** Opening Reports clears its number (quietly: it isn't an edit to the account). */
    public static function sawReports(User $user): void
    {
        $now = now();
        User::query()->whereKey($user->id)->toBase()->update(['reports_seen_at' => $now]);
        $user->setAttribute('reports_seen_at', $now)->syncOriginalAttribute('reports_seen_at');
    }
}
