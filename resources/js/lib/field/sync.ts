/**
 * Sync layer for the offline field app — fetch the day's bundle (network with
 * cache fallback) and drain the visit-completion queue against the idempotent
 * field API. Network failures keep work queued; only a definitive 4xx (bad
 * request / not authorized) marks an item failed.
 */
import { postJson } from '@/lib/http';
import { allQueued, enqueue, getBundle, patchQueued, removeQueued, saveBundle, type QueuedVisit, type TodayBundle } from './store';

export interface LoadResult {
    bundle: TodayBundle | null;
    source: 'network' | 'cache' | 'none';
}

/** Load today's route: try the network, fall back to the last cached bundle. */
export async function loadToday(date?: string): Promise<LoadResult> {
    const today = date ?? new Date().toISOString().slice(0, 10);
    try {
        const res = await fetch(`/api/field/today${date ? `?date=${date}` : ''}`, {
            credentials: 'same-origin',
            headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        });
        if (!res.ok) throw new Error(String(res.status));
        const bundle = (await res.json()) as TodayBundle;
        await saveBundle(bundle);
        return { bundle, source: 'network' };
    } catch {
        const cached = await getBundle(today);
        return { bundle: cached, source: cached ? 'cache' : 'none' };
    }
}

const uuid = (): string =>
    typeof crypto !== 'undefined' && 'randomUUID' in crypto ? crypto.randomUUID() : `k-${Date.now()}-${Math.floor(Math.random() * 1e9)}`;

/** Queue a visit completion and try to flush it immediately. Returns the queue key. */
export async function queueCompletion(stopId: number, poolName: string, payload: Record<string, unknown>): Promise<string> {
    const key = uuid();
    await enqueue({
        idempotency_key: key,
        stop_id: stopId,
        pool_name: poolName,
        payload: { ...payload, idempotency_key: key },
        status: 'pending',
        error: null,
        created_at: Date.now(),
    });
    void flushQueue();
    return key;
}

let flushing = false;

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
    }
    return (await allQueued()).length;
}

async function sync(item: QueuedVisit): Promise<void> {
    let res: Response;
    try {
        res = await postJson(`/api/field/visits/${item.stop_id}/complete`, item.payload);
    } catch {
        return; // Offline / network blip — keep it queued for the next flush.
    }
    if (res.ok) {
        await removeQueued(item.idempotency_key);
        return;
    }
    // A definitive client error won't succeed on retry — surface it, stop retrying.
    if (res.status >= 400 && res.status < 500 && res.status !== 419) {
        await patchQueued(item.idempotency_key, { status: 'failed', error: `Server rejected (${res.status})` });
    }
    // 419 (CSRF) / 5xx stay pending for a later retry.
}

export async function queuedCount(): Promise<number> {
    return (await allQueued()).length;
}
