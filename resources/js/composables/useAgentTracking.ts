import { postJson } from '@/lib/http';
import { ref } from 'vue';

const STORAGE_KEY = 'rp_field_tracking';
const MIN_INTERVAL_MS = 25_000;

/**
 * Agent live location sharing: a consent-gated GPS watcher that pings the server
 * (throttled to ~once per 25 s) while the agent is on shift. The server stops it
 * (responds tracking:false) once the day's routes are done.
 *
 * State is a module-level singleton so the sidebar control and the field app share
 * one watch + one `sharing` flag, and the watch survives SPA navigation between
 * pages (it lives at module scope, not tied to any component's lifecycle). The
 * agent's choice persists in localStorage across reloads.
 *
 * The browser geolocation prompt only ever appears from an explicit tap (start):
 * restore() resumes on mount only when permission is *already* granted, so opening
 * a page or the sidebar never triggers a prompt.
 */
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

function onWatchError(err: GeolocationPositionError): void {
    // If the agent denies (or revokes) permission, don't leave the toggle "on".
    if (err.code === err.PERMISSION_DENIED) stop();
}

function ensureWatch(): void {
    if (watchId !== null || !hasGeo()) return;
    lastSent = 0;
    watchId = navigator.geolocation.watchPosition(send, onWatchError, { enableHighAccuracy: true, maximumAge: 20_000, timeout: 15_000 });
}

function clearWatch(): void {
    if (watchId !== null && hasGeo()) navigator.geolocation.clearWatch(watchId);
    watchId = null;
}

/** Turn sharing on — the only path that may surface the geolocation prompt. */
function start(): void {
    sharing.value = true;
    if (typeof localStorage !== 'undefined') localStorage.setItem(STORAGE_KEY, '1');
    ensureWatch();
}

function stop(): void {
    sharing.value = false;
    if (typeof localStorage !== 'undefined') localStorage.setItem(STORAGE_KEY, '0');
    clearWatch();
}

const toggle = (): void => (sharing.value ? stop() : start());

/**
 * Resume the agent's prior choice on mount — but only silently, when geolocation
 * is already granted. Never prompts (that's reserved for an explicit tap).
 */
async function restore(): Promise<void> {
    if (typeof localStorage === 'undefined' || localStorage.getItem(STORAGE_KEY) !== '1') return;
    if (typeof navigator !== 'undefined' && navigator.permissions?.query) {
        try {
            const status = await navigator.permissions.query({ name: 'geolocation' as PermissionName });
            if (status.state === 'granted') {
                sharing.value = true;
                ensureWatch();
            }
            return;
        } catch {
            /* Permissions API unavailable — fall through to the safe default */
        }
    }
    // No Permissions API: don't auto-start, to avoid an unprompted geolocation request.
}

export function useAgentTracking() {
    return { sharing, toggle, restore };
}
