import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

declare global {
    interface Window {
        Pusher: typeof Pusher;
        Echo?: Echo<'reverb'>;
    }
}

let echo: Echo<'reverb'> | null = null;

/**
 * Lazily create the singleton Echo client (Reverb / Pusher protocol). Returns
 * null during SSR or when no Reverb key is configured, so callers no-op safely.
 */
export function initEcho(): Echo<'reverb'> | null {
    if (typeof window === 'undefined') return null;
    if (echo) return echo;

    const key = import.meta.env.VITE_REVERB_APP_KEY;
    if (!key) return null;

    window.Pusher = Pusher;
    echo = new Echo<'reverb'>({
        broadcaster: 'reverb',
        key,
        wsHost: import.meta.env.VITE_REVERB_HOST,
        wsPort: Number(import.meta.env.VITE_REVERB_PORT ?? 80),
        wssPort: Number(import.meta.env.VITE_REVERB_PORT ?? 443),
        forceTLS: (import.meta.env.VITE_REVERB_SCHEME ?? 'https') === 'https',
        enabledTransports: ['ws', 'wss'],
    });
    window.Echo = echo;

    return echo;
}

let subscribedUserId: number | null = null;

/**
 * Keep the private notification subscription in sync with the current user.
 * Idempotent across Inertia navigations; re-subscribes on login / impersonation
 * (user changes) and leaves the channel on logout (userId null). Safe to call
 * every navigation.
 */
export function ensureUserSubscription(userId: number | null, onNotification: () => void): void {
    if (userId === subscribedUserId) return;

    const client = initEcho();
    if (!client) return;

    if (subscribedUserId !== null) client.leave(`App.Models.User.${subscribedUserId}`);
    subscribedUserId = userId;

    if (userId !== null) client.private(`App.Models.User.${userId}`).notification(() => onNotification());
}

/**
 * Subscribe to a tenant's live schedule feed (RouteUpdated events). Returns an
 * unsubscribe fn; no-ops (returns a noop) during SSR / without a key.
 */
export function subscribeTenantRoutes(tenantId: number, onUpdate: (e: { date: string; agent_id: number | null }) => void): () => void {
    const client = initEcho();
    if (!client) return () => {};

    client.private(`tenant.${tenantId}`).listen('.RouteUpdated', onUpdate);

    return () => client.leave(`tenant.${tenantId}`);
}
