import { postJson } from '@/lib/http';
import { ref } from 'vue';

const STORAGE_KEY = 'rp_field_tracking';
const MIN_INTERVAL_MS = 25_000;

/**
 * Field-app live location sharing: a consent-gated GPS watcher that pings the
 * server (throttled to ~once per 25 s) while the agent is on shift. The server
 * stops it (responds tracking:false) once the day's routes are done. The
 * agent's choice persists across reloads; the watch is only active while the
 * field screen is mounted.
 */
export function useAgentTracking() {
    const sharing = ref(false);
    let watchId: number | null = null;
    let lastSent = 0;

    const hasGeo = (): boolean => typeof navigator !== 'undefined' && !!navigator.geolocation;

    async function send(pos: GeolocationPosition): Promise<void> {
        const now = Date.now();
        if (now - lastSent < MIN_INTERVAL_MS) return;
        lastSent = now;
        try {
            const res = await postJson('/api/field/ping', {
                lat: pos.coords.latitude,
                lng: pos.coords.longitude,
                heading: pos.coords.heading != null && !Number.isNaN(pos.coords.heading) ? Math.round(pos.coords.heading) : null,
                accuracy: pos.coords.accuracy != null ? Math.round(pos.coords.accuracy) : null,
            });
            const data = await res.json().catch(() => ({}));
            if (data?.tracking === false) stop(); // shift over → stop sharing
        } catch {
            /* offline / transient — keep watching, retry on the next position */
        }
    }

    function ensureWatch(): void {
        if (watchId !== null || !hasGeo()) return;
        lastSent = 0;
        watchId = navigator.geolocation.watchPosition(send, () => {}, { enableHighAccuracy: true, maximumAge: 20_000, timeout: 15_000 });
    }

    function clearWatch(): void {
        if (watchId !== null && hasGeo()) navigator.geolocation.clearWatch(watchId);
        watchId = null;
    }

    function start(): void {
        sharing.value = true;
        localStorage.setItem(STORAGE_KEY, '1');
        ensureWatch();
    }

    function stop(): void {
        sharing.value = false;
        localStorage.setItem(STORAGE_KEY, '0');
        clearWatch();
    }

    const toggle = (): void => (sharing.value ? stop() : start());

    /** Resume the agent's prior choice on mount. */
    function restore(): void {
        if (typeof localStorage !== 'undefined' && localStorage.getItem(STORAGE_KEY) === '1') {
            sharing.value = true;
            ensureWatch();
        }
    }

    /** Stop the watch on unmount without forgetting the agent's choice. */
    const cleanup = (): void => clearWatch();

    return { sharing, toggle, restore, cleanup };
}
