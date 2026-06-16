/**
 * IndexedDB store for the offline field app: the cached "today" bundle and the
 * outbound mutation queue (visit completions awaiting sync). All access is
 * client-only — call these from onMounted / event handlers, never during SSR.
 */
import { openDB, type DBSchema, type IDBPDatabase } from 'idb';

export interface FieldStop {
    id: number;
    order: number;
    status: string;
    completed: boolean;
    eta: string | null;
    pool: {
        id: number;
        name: string;
        type: string | null;
        sanitizer: string | null;
        volume_gallons: number | null;
        custom_target_ranges: Record<string, { min?: number; max?: number }> | null;
        customer: string;
        phone: string | null;
        gate_code: string | null;
        access_notes: string | null;
        lat: number | null;
        lng: number | null;
    } | null;
    service: { name: string | null; tasks: string[] };
    last_reading: Record<string, number | null> | null;
}

export interface TodayBundle {
    date: string;
    generated_at: string;
    agent: { id: number; name: string };
    stops: FieldStop[];
    inventory: { id: number; name: string; unit: string | null; stock: number }[];
}

export interface QueuedVisit {
    idempotency_key: string;
    stop_id: number;
    pool_name: string;
    payload: Record<string, unknown>;
    status: 'pending' | 'failed';
    error: string | null;
    created_at: number;
}

interface FieldDB extends DBSchema {
    bundles: { key: string; value: { date: string; data: TodayBundle; cached_at: number } };
    queue: { key: string; value: QueuedVisit };
}

let dbPromise: Promise<IDBPDatabase<FieldDB>> | null = null;

function db(): Promise<IDBPDatabase<FieldDB>> {
    dbPromise ??= openDB<FieldDB>('routepilot-field', 1, {
        upgrade(database) {
            database.createObjectStore('bundles', { keyPath: 'date' });
            database.createObjectStore('queue', { keyPath: 'idempotency_key' });
        },
    });
    return dbPromise;
}

export async function saveBundle(data: TodayBundle): Promise<void> {
    const database = await db();
    await database.put('bundles', { date: data.date, data, cached_at: Date.now() });
}

export async function getBundle(date: string): Promise<TodayBundle | null> {
    const database = await db();
    return (await database.get('bundles', date))?.data ?? null;
}

export async function enqueue(item: QueuedVisit): Promise<void> {
    const database = await db();
    await database.put('queue', item);
}

export async function allQueued(): Promise<QueuedVisit[]> {
    const database = await db();
    return (await database.getAll('queue')).sort((a, b) => a.created_at - b.created_at);
}

export async function removeQueued(key: string): Promise<void> {
    const database = await db();
    await database.delete('queue', key);
}

export async function patchQueued(key: string, patch: Partial<QueuedVisit>): Promise<void> {
    const database = await db();
    const existing = await database.get('queue', key);
    if (existing) await database.put('queue', { ...existing, ...patch });
}
