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

/** Subscribe to a user's private notification channel; fires onNotification on each push. */
export function subscribeUserNotifications(userId: number, onNotification: () => void): void {
    const client = initEcho();
    if (!client) return;

    client.private(`App.Models.User.${userId}`).notification(() => onNotification());
}
