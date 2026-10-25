import { router } from '@inertiajs/vue3';

/**
 * Notifications outside the page. A desktop gets a pop-up in the corner while
 * RoutePilot is open (FlashToast); phones and tablets get real notifications
 * through web push, even when it's closed (public/sw.js shows them,
 * App\Jobs\SendWebPush sends them). Both are switched per device.
 */

/** A mouse-driven screen: pop-ups here, phone notifications everywhere else. */
export const isDesktop = (): boolean => typeof window !== 'undefined' && window.matchMedia('(hover: hover) and (pointer: fine)').matches;

// ---- Pop-ups (this computer) ----

const POPUPS_KEY = 'rp.popups';

export function popupsOn(): boolean {
    try {
        return localStorage.getItem(POPUPS_KEY) !== 'off';
    } catch {
        return true;
    }
}

export function setPopups(on: boolean): void {
    try {
        if (on) localStorage.removeItem(POPUPS_KEY);
        else localStorage.setItem(POPUPS_KEY, 'off');
    } catch {
        // Storage blocked (private window): pop-ups simply stay on.
    }
}

// ---- Phone notifications (this phone) ----

export type PushState = 'unsupported' | 'blocked' | 'off' | 'on';

export const pushSupported = (): boolean =>
    typeof window !== 'undefined' && 'serviceWorker' in navigator && 'PushManager' in window && 'Notification' in window;

async function subscription(): Promise<PushSubscription | null> {
    const registration = await navigator.serviceWorker.getRegistration();
    return (await registration?.pushManager.getSubscription()) ?? null;
}

export async function pushState(): Promise<PushState> {
    if (!pushSupported()) return 'unsupported';
    if (Notification.permission === 'denied') return 'blocked';
    return Notification.permission === 'granted' && (await subscription()) ? 'on' : 'off';
}

/** Ask the browser, subscribe this phone, and tell the server where to send. */
export async function turnOnPush(publicKey: string): Promise<PushState> {
    const permission = await Notification.requestPermission();
    if (permission !== 'granted') return permission === 'denied' ? 'blocked' : 'off';

    await navigator.serviceWorker.register('/sw.js');
    const registration = await navigator.serviceWorker.ready;
    const sub = await registration.pushManager.subscribe({ userVisibleOnly: true, applicationServerKey: fromBase64Url(publicKey) });
    router.post('/push-subscriptions', sub.toJSON() as Record<string, never>, { preserveScroll: true, preserveState: true });

    return 'on';
}

export async function turnOffPush(): Promise<PushState> {
    const sub = await subscription();
    if (sub) {
        await sub.unsubscribe();
        router.delete('/push-subscriptions', { data: { endpoint: sub.endpoint }, preserveScroll: true, preserveState: true });
    }
    return 'off';
}

/** Log out, stopping this phone's notifications first so the next person to pick it up doesn't get them. */
export async function logOut(): Promise<void> {
    try {
        if (pushSupported()) await (await subscription())?.unsubscribe();
    } catch {
        // Log out regardless; the server forgets the phone on its next failed send.
    }
    router.post('/logout');
}

function fromBase64Url(value: string): Uint8Array<ArrayBuffer> {
    const base64 = (value + '='.repeat((4 - (value.length % 4)) % 4)).replace(/-/g, '+').replace(/_/g, '/');
    return Uint8Array.from(atob(base64), (c) => c.charCodeAt(0));
}
