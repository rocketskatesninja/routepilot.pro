<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\DB;

/**
 * Weekly housekeeping: prune read in-app notifications and techs' stale
 * last-known positions, and clear old website leads' IPs. Runs platform-wide
 * from the console with no bound tenant, so every tenant is swept.
 *
 * It never deletes customers, reports or billing records: an archived customer
 * stays archived until someone deletes them for good from People → Archived.
 */
class DataRetention
{
    /**
     * @return array{notifications_pruned: int, agent_locations_pruned: int, lead_ips_cleared: int, chats_pruned: int}
     */
    public function purge(bool $apply = true, ?int $notificationDays = null): array
    {
        $notificationDays ??= (int) config('retention.read_notification_days');

        return [
            'notifications_pruned' => $this->pruneReadNotifications($notificationDays, $apply),
            'agent_locations_pruned' => $this->pruneStaleLocations($apply),
            'lead_ips_cleared' => $this->clearLeadIps($apply),
            'chats_pruned' => $this->pruneChats($apply),
        ];
    }

    /** Clear (or count) the IP a website lead was sent from once it's old; the lead itself stays. */
    private function clearLeadIps(bool $apply): int
    {
        $days = (int) config('retention.lead_ip_days');
        if ($days <= 0) {
            return 0;
        }

        $query = DB::table('leads')->whereNotNull('ip_address')->where('created_at', '<', now()->subDays($days));

        return $apply ? $query->update(['ip_address' => null]) : $query->count();
    }

    /**
     * Prune (or count) a tech's last-known position once it's over a week old —
     * one row per tech, overwritten by every ping, and the live map only shows
     * the last 30 minutes. A visit's own completion location stays on its report.
     */
    /** Conversations nobody has written in for `chat_days` (messages cascade). */
    private function pruneChats(bool $apply): int
    {
        $days = (int) config('retention.chat_days');
        if ($days <= 0) {
            return 0;
        }

        $cutoff = now()->subDays($days);
        $query = DB::table('chat_sessions')
            ->where('created_at', '<', $cutoff)
            ->whereNotExists(fn ($m) => $m->from('chat_messages')->whereColumn('chat_messages.chat_session_id', 'chat_sessions.id')->where('chat_messages.created_at', '>=', $cutoff));

        return $apply ? $query->delete() : $query->count();
    }

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
