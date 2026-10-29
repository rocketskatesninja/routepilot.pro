/**
 * Sync layer for the offline field app — fetch the day's bundle (network with
 * cache fallback) and drain the visit-completion queue against the idempotent
 * field API. Network failures keep work queued; only a definitive 4xx (bad
 * request / not authorized) marks an item failed.
 */
import { postForm, postJson } from '@/lib/http';
import { isSignedOut } from './session';
import {
    allQueued,
    enqueue,
    getBundle,
    patchQueued,
    removeQueued,
    saveBundle,
    saveDraft,
    saveDraftPhotos,
    type QueuedVisit,
    type TodayBundle,
    type VisitDraft,
} from './store';

export interface LoadResult {
    bundle: TodayBundle | null;
    source: 'network' | 'cache' | 'none';
}

/**
 * Load today's route: try the network, fall back to the last cached bundle. The
 * office can ask for a tech's route (`agent`) — that's online only, and never
 * cached, so it can't replace the office person's own route on this device.
 */
export async function loadToday(date?: string, agent?: number): Promise<LoadResult> {
    const today = date ?? new Date().toISOString().slice(0, 10);
    const query = new URLSearchParams({ ...(date ? { date } : {}), ...(agent ? { agent: String(agent) } : {}) }).toString();
    try {
        const res = await fetch(`/api/field/today${query ? `?${query}` : ''}`, {
            credentials: 'same-origin',
            headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        });
        isSignedOut(res); // the page says so, and shows the last saved route meanwhile
        if (!res.ok) throw new Error(String(res.status));
        const bundle = (await res.json()) as TodayBundle;
        if (!agent) await saveBundle(bundle);
        return { bundle, source: 'network' };
    } catch {
        const cached = agent ? null : await getBundle(today);
        return { bundle: cached, source: cached ? 'cache' : 'none' };
    }
}

const uuid = (): string =>
    typeof crypto !== 'undefined' && 'randomUUID' in crypto ? crypto.randomUUID() : `k-${Date.now()}-${Math.floor(Math.random() * 1e9)}`;

/** Queue a visit completion and try to flush it immediately. Returns the queue key. */
export async function queueCompletion(stopId: number, poolName: string, payload: Record<string, unknown>, photos: File[] = []): Promise<string> {
    const key = uuid();
    await enqueue({
        idempotency_key: key,
        stop_id: stopId,
        pool_name: poolName,
        payload: { ...payload, idempotency_key: key },
        photos,
        status: 'pending',
        error: null,
        created_at: Date.now(),
    });
    void flushQueue();
    return key;
}

/** Flatten a payload value into FormData keys Laravel parses (tasks[0][name], …). */
function appendField(fd: FormData, key: string, val: unknown): void {
    if (val === null || val === undefined) return;
    if (Array.isArray(val)) {
        val.forEach((item, i) => {
            if (item !== null && typeof item === 'object') {
                for (const [k, v] of Object.entries(item)) appendField(fd, `${key}[${i}][${k}]`, v);
            } else {
                appendField(fd, `${key}[${i}]`, item);
            }
        });
    } else if (typeof val === 'boolean') {
        fd.append(key, val ? '1' : '0');
    } else {
        fd.append(key, String(val));
    }
}

let flushing = false;

/** Fired on window whenever a sync pass finishes, so screens can refresh their counts. */
export const QUEUE_SYNCED = 'field-queue-synced';

/** Drain the queue. Returns how many remain unsynced afterward. */
export async function flushQueue(): Promise<number> {
    if (flushing) return (await allQueued()).length;
    flushing = true;
    try {
        for (const item of await allQueued()) {
            await sync(item);
        }
    } finally {
        flushing = false;
        if (typeof window !== 'undefined') window.dispatchEvent(new Event(QUEUE_SYNCED));
    }
    return (await allQueued()).length;
}

async function sync(item: QueuedVisit): Promise<void> {
    const url = `/api/field/visits/${item.stop_id}/complete`;
    let res: Response;
    try {
        if (item.photos && item.photos.length) {
            // With photos → multipart (same shape the online form posts); otherwise JSON.
            const fd = new FormData();
            for (const [k, v] of Object.entries(item.payload)) appendField(fd, k, v);
            for (const photo of item.photos) fd.append('photos[]', photo);
            res = await postForm(url, fd);
        } else {
            res = await postJson(url, item.payload);
        }
    } catch {
        return; // Offline / network blip — keep it queued for the next flush.
    }
    if (res.ok) {
        await removeQueued(item.idempotency_key);
        return;
    }
    // Signed out: the visit is fine, it just needs the person signed in again — keep it waiting.
    if (isSignedOut(res)) return;
    // A definitive client error won't succeed on retry — surface it (with the server's reason), stop retrying.
    if (res.status >= 400 && res.status < 500 && res.status !== 419) {
        await patchQueued(item.idempotency_key, { status: 'failed', error: await refusal(res) });
    }
    // 419 (CSRF) / 5xx stay pending for a later retry.
}

export async function queuedCount(): Promise<number> {
    return (await allQueued()).length;
}

export async function failedCount(): Promise<number> {
    return (await allQueued()).filter((i) => i.status === 'failed').length;
}

/** The first visit that didn't sync, for the banner: its stop, its pool and the server's reason (null when none failed). */
export async function firstFailed(): Promise<Pick<QueuedVisit, 'stop_id' | 'pool_name' | 'error'> | null> {
    const item = (await allQueued()).find((i) => i.status === 'failed');
    return item ? { stop_id: item.stop_id, pool_name: item.pool_name, error: item.error } : null;
}

/** Drop a refused visit for good, every failed copy for its stop: what was entered for it is lost. */
export async function discardFailed(stopId: number): Promise<void> {
    for (const item of await allQueued()) {
        if (item.status === 'failed' && item.stop_id === stopId) await removeQueued(item.idempotency_key);
    }
}

/**
 * Take a refused visit back out of the queue as its stop's draft, so the tech can
 * fix what the server refused and finish it again (resending would only send the
 * same thing). Every failed copy for the stop goes; the newest one's entries return.
 */
export async function reopenFailed(stopId: number): Promise<void> {
    const failed = (await allQueued()).filter((i) => i.status === 'failed' && i.stop_id === stopId);
    if (!failed.length) return;
    const latest = failed.reduce((a, b) => (b.created_at > a.created_at ? b : a));
    await saveDraft(draftFrom(latest));
    if (latest.photos?.length) await saveDraftPhotos(stopId, latest.photos);
    for (const item of failed) await removeQueued(item.idempotency_key);
}

/** A queued visit back in the shape the visit form keeps its draft in. */
function draftFrom(item: QueuedVisit): VisitDraft {
    const p = item.payload;
    const text = (v: unknown): string => (typeof v === 'string' ? v : '');
    const rows = (v: unknown): Record<string, unknown>[] => (Array.isArray(v) ? v.filter((r) => r !== null && typeof r === 'object') : []);
    return {
        stop_id: item.stop_id,
        // Every number sent; the form only reads back its own reading boxes.
        reading: Object.fromEntries(Object.entries(p).flatMap(([k, v]) => (typeof v === 'number' ? [[k, String(v)]] : []))),
        tasks: rows(p.tasks).map((t) => ({ name: text(t.name), done: t.done === true })),
        // Picked chemicals as the form keeps them; typed ones (older copies of the app) keep their name for the form to match.
        treatments: rows(p.treatments).map((t) =>
            t.chemical_id != null ? { chemical_id: Number(t.chemical_id), amount: t.amount != null ? String(t.amount) : '' } : t,
        ) as VisitDraft['treatments'],
        notes: text(p.notes),
        office_notes: text(p.office_notes),
        work_order_form: {
            title: text(p.work_order_title),
            details: text(p.work_order_details),
            urgent: p.work_order_urgent === true,
            price: p.work_order_price != null ? String(p.work_order_price) : '',
        },
        analyzed: true,
        updated_at: Date.now(),
    };
}

/** The server's reason for refusing a visit: the first field error of a 422 ("The photo must be an image."), else its message. */
async function refusal(res: Response): Promise<string> {
    const data = (await res.json().catch(() => null)) as { message?: unknown; errors?: Record<string, unknown[]> } | null;
    const first = data?.errors ? Object.values(data.errors).flat()[0] : null;
    if (typeof first === 'string') return first;
    return typeof data?.message === 'string' && data.message !== '' ? data.message : `Server rejected (${res.status})`;
}

/** Reset failed items to pending and flush again (manual retry). */
export async function retryFailed(): Promise<number> {
    for (const item of await allQueued()) {
        if (item.status === 'failed') await patchQueued(item.idempotency_key, { status: 'pending', error: null });
    }
    return flushQueue();
}
