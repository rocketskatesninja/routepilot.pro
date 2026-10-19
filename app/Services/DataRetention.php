<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\DB;

/**
 * Weekly housekeeping: prune read in-app notifications and techs' stale
 * last-known positions. Runs platform-wide from the console with no bound
 * tenant, so every tenant is swept.
 *
 * It never deletes customers, reports or billing records: an archived customer
 * stays archived until someone deletes them for good from People → Archived.
 */
class DataRetention
{
    /**
     * @return array{notifications_pruned: int, agent_locations_pruned: int}
     */
    public function purge(bool $apply = true, ?int $notificationDays = null): array
    {
        $notificationDays ??= (int) config('retention.read_notification_days');

        return [
            'notifications_pruned' => $this->pruneReadNotifications($notificationDays, $apply),
            'agent_locations_pruned' => $this->pruneStaleLocations($apply),
        ];
    }

    /**
     * Prune (or count) a tech's last-known position once it's over a week old —
     * one row per tech, overwritten by every ping, and the live map only shows
     * the last 30 minutes. A visit's own completion location stays on its report.
     */
    private function pruneStaleLocations(bool $apply): int
    {
        $days = (int) config('retention.agent_location_days');
        if ($days <= 0) {
            return 0;
        }

        $query = DB::table('agent_locations')->where('recorded_at', '<', now()->subDays($days));

        return $apply ? $query->delete() : $query->count();
    }

    private function pruneReadNotifications(int $days, bool $apply): int
    {
        if ($days <= 0) {
            return 0;
        }

        $query = DB::table('notifications')
            ->whereNotNull('read_at')
            ->where('created_at', '<', now()->subDays($days));

        return $apply ? $query->delete() : $query->count();
    }
}
