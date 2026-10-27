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
    // Added in the field (not on a plan, not billed); absent on bundles cached before it existed.
    extra?: boolean;
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
        report_cc?: string | null; // who else gets the report (absent on bundles cached before it existed)
        lat: number | null;
        lng: number | null;
    } | null;
    // `modules` = which visit sections to show (absent on bundles cached before it existed → all on).
    service: { name: string | null; tasks: string[]; modules?: Record<string, boolean> };
    last_reading: Record<string, number | null> | null;
    // Open work orders on this pool, read-only (absent on bundles cached before it existed).
    work_orders?: FieldWorkOrder[];
}

export interface FieldWorkOrder {
    id: number;
    title: string;
    details: string | null;
    urgent: boolean;
    overdue: boolean;
    due_on: string | null;
    assignee: string | null;
    mine: boolean; // assigned to the tech looking at it
}

export interface TodayBundle {
    date: string;
    generated_at: string;
    agent: { id: number; name: string };
    stops: FieldStop[];
    inventory: FieldChemical[];
}

export interface QueuedVisit {
    idempotency_key: string;
    stop_id: number;
    pool_name: string;
    payload: Record<string, unknown>;
    // Visit photos, kept as Blobs so they survive in the offline queue and upload
    // (as multipart) when the completion syncs. Absent/empty = no photos.
    photos?: File[];
    status: 'pending' | 'failed';
    error: string | null;
    created_at: number;
}

/** The visit's "Work order" section: something to do later, for the company's list. */
export interface WorkOrderDraft {
    title: string;
    details: string;
    urgent: boolean;
    price: string; // what to charge for it, if the tech knows
}

/** A chemical from the company's list: what a tech picks, in its one unit. `key` = which standard chemical it is, if any. */
export interface FieldChemical {
    id: number;
    name: string;
    unit: string;
    key?: string | null; // absent on bundles cached before the chemical list
}

/** A chemical on the visit: picked from the list, a whole number of its unit. */
export interface TreatmentDraft {
    chemical_id: number | null;
    amount: string;
}

/** A visit the tech has started but not finished — kept so a page reload doesn't lose it. */
export interface VisitDraft {
    stop_id: number;
    reading: Record<string, string>;
    tasks: { name: string; done: boolean }[];
    treatments: TreatmentDraft[]; // drafts from before the chemical list hold typed {name, amount, unit} rows
    notes: string;
    office_notes?: string; // absent on drafts saved before the office note existed
    report_cc?: string; // likewise, before the copy-to address
    work_order_form?: WorkOrderDraft; // likewise, before the work order section
    work_order?: string; // the first version's single box (with work_order_urgent)
    work_order_urgent?: boolean;
    analyzed: boolean;
    updated_at: number;
}

interface FieldDB extends DBSchema {
    bundles: { key: string; value: { date: string; data: TodayBundle; cached_at: number } };
    queue: { key: string; value: QueuedVisit };
    // Photos live apart from the typed-in fields so saving a keystroke never rewrites the images.
    drafts: { key: number; value: VisitDraft };
    draft_photos: { key: number; value: { stop_id: number; photos: File[]; updated_at: number } };
}

let dbPromise: Promise<IDBPDatabase<FieldDB>> | null = null;

function db(): Promise<IDBPDatabase<FieldDB>> {
    dbPromise ??= openDB<FieldDB>('routepilot-field', 2, {
        upgrade(database, oldVersion) {
            if (oldVersion < 1) {
                database.createObjectStore('bundles', { keyPath: 'date' });
                database.createObjectStore('queue', { keyPath: 'idempotency_key' });
            }
            if (oldVersion < 2) {
                database.createObjectStore('drafts', { keyPath: 'stop_id' });
                database.createObjectStore('draft_photos', { keyPath: 'stop_id' });
            }
        },
    });
    return dbPromise;
}

export async function saveBundle(data: TodayBundle): Promise<void> {
    const database = await db();
    // `data` is often a Vue-reactive proxy (e.g. Index.vue re-saving `bundle.value` after
    // marking a stop completed) — IndexedDB's structured-clone step rejects that outright
    // (DOMException: "Proxy object could not be cloned"), so round-trip through JSON first.
    await database.put('bundles', JSON.parse(JSON.stringify({ date: data.date, data, cached_at: Date.now() })));
}

export async function getBundle(date: string): Promise<TodayBundle | null> {
    const database = await db();
    return (await database.get('bundles', date))?.data ?? null;
}

export async function enqueue(item: QueuedVisit): Promise<void> {
    const database = await db();
    // The JSON-serialisable parts are built from Vue-reactive form state; round-trip
    // through JSON as cheap insurance against the Proxy-clone failure fixed in
    // saveBundle() above. Photo Blobs are kept raw — JSON would destroy them, and
    // IndexedDB clones Blobs fine.
    const { photos, ...rest } = item;
    const clean: QueuedVisit = JSON.parse(JSON.stringify(rest));
    if (photos && photos.length) clean.photos = Array.from(photos);
    await database.put('queue', clean);
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

export async function getDraft(stopId: number): Promise<{ draft: VisitDraft | null; photos: File[] }> {
    const database = await db();
    const [draft, photos] = await Promise.all([database.get('drafts', stopId), database.get('draft_photos', stopId)]);
    return { draft: draft ?? null, photos: photos?.photos ?? [] };
}

export async function saveDraft(draft: VisitDraft): Promise<void> {
    const database = await db();
    // Built from Vue-reactive form state — see saveBundle() on why the JSON round trip.
    await database.put('drafts', JSON.parse(JSON.stringify(draft)));
}

export async function saveDraftPhotos(stopId: number, photos: File[]): Promise<void> {
    const database = await db();
    await database.put('draft_photos', { stop_id: stopId, photos: Array.from(photos), updated_at: Date.now() });
}

export async function deleteDraft(stopId: number): Promise<void> {
    const database = await db();
    const tx = database.transaction(['drafts', 'draft_photos'], 'readwrite');
    await Promise.all([tx.objectStore('drafts').delete(stopId), tx.objectStore('draft_photos').delete(stopId), tx.done]);
}

/** Drop drafts nobody has touched in `maxAgeMs` — stops from days already over. */
export async function pruneDrafts(maxAgeMs: number): Promise<void> {
    const database = await db();
    const cutoff = Date.now() - maxAgeMs;
    const tx = database.transaction(['drafts', 'draft_photos'], 'readwrite');
    for (const name of ['drafts', 'draft_photos'] as const) {
        let cursor = await tx.objectStore(name).openCursor();
        while (cursor) {
            if (cursor.value.updated_at < cutoff) await cursor.delete();
            cursor = await cursor.continue();
        }
    }
    await tx.done;
}
