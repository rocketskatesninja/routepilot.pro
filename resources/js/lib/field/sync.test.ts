import 'fake-indexeddb/auto';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { signedOut } from './session';
import { allQueued, getBundle, getDraft, removeQueued, type TodayBundle } from './store';
import { discardFailed, failedCount, firstFailed, flushQueue, loadToday, QUEUE_SYNCED, queueCompletion, reopenFailed, retryFailed } from './sync';

/**
 * The phone side of offline visits: what stays on the device, what is sent again,
 * and what is given up on. The server side (one visit per key) is FieldApiTest.
 */

const fetchMock = vi.fn<(url: string, init?: RequestInit) => Promise<Response>>();
vi.stubGlobal('fetch', fetchMock);
vi.stubGlobal('document', { cookie: '' });
vi.stubGlobal('window', new EventTarget());

const json = (status: number, body: unknown = {}) => new Response(JSON.stringify(body), { status, headers: { 'Content-Type': 'application/json' } });
const offline = () => Promise.reject(new TypeError('Failed to fetch'));

/** Finish a visit and wait for the send it kicks off to end. */
async function finish(stopId: number, payload: Record<string, unknown> = { ph: 7.4 }, photos: File[] = []): Promise<string> {
    const sent = new Promise((resolve) => window.addEventListener(QUEUE_SYNCED, resolve, { once: true }));
    const key = await queueCompletion(stopId, `Pool ${stopId}`, payload, photos);
    await sent;
    return key;
}

/** The body of the nth request, as the server would read it. */
const sentBody = (call = 0) => JSON.parse(String(fetchMock.mock.calls[call][1]?.body)) as Record<string, unknown>;

beforeEach(async () => {
    for (const item of await allQueued()) await removeQueued(item.idempotency_key);
    fetchMock.mockReset();
    signedOut.value = false;
});

describe('finishing a visit', () => {
    it('sends it and clears it from the device when the server takes it', async () => {
        fetchMock.mockResolvedValue(json(200));
        const key = await finish(1);

        expect(fetchMock).toHaveBeenCalledOnce();
        expect(fetchMock.mock.calls[0][0]).toBe('/api/field/visits/1/complete');
        expect(sentBody()).toEqual({ ph: 7.4, idempotency_key: key });
        expect(await allQueued()).toEqual([]);
    });

    it('keeps it on the device with no signal, and sends it with the same key later', async () => {
        fetchMock.mockImplementation(offline);
        const key = await finish(2);

        expect(await allQueued()).toMatchObject([{ stop_id: 2, status: 'pending', error: null }]);

        // Signal is back. The key is what stops the server recording the visit twice.
        fetchMock.mockResolvedValue(json(200));
        expect(await flushQueue()).toBe(0);
        expect(sentBody(1).idempotency_key).toBe(key);
    });

    it('keeps trying after a server error', async () => {
        fetchMock.mockResolvedValue(json(500));
        await finish(3);

        expect(await allQueued()).toMatchObject([{ status: 'pending' }]);
        expect(await failedCount()).toBe(0);
    });

    it('keeps it waiting when the tech has been signed out, and says so', async () => {
        fetchMock.mockResolvedValue(json(419));
        await finish(4);

        expect(await allQueued()).toMatchObject([{ status: 'pending' }]);
        expect(signedOut.value).toBe(true);
    });

    it('sends photos as a form upload', async () => {
        fetchMock.mockResolvedValue(json(200));
        const photo = new File(['x'], 'pool.jpg', { type: 'image/jpeg' });
        const key = await finish(5, { ph: 7.2, tasks: [{ name: 'Brush', done: true }] }, [photo]);

        const body = fetchMock.mock.calls[0][1]?.body as FormData;
        expect(body).toBeInstanceOf(FormData);
        expect(body.get('idempotency_key')).toBe(key);
        expect(body.get('tasks[0][name]')).toBe('Brush');
        expect(body.get('tasks[0][done]')).toBe('1');
        expect((body.get('photos[]') as Blob).size).toBe(1); // (the test database hands back a nameless copy)
    });
});

describe('a visit the server refuses', () => {
    const refused = () => json(422, { message: 'Invalid.', errors: { ph: ['The pH must be between 0 and 14.'] } });

    it('stops being sent, and shows the server’s reason', async () => {
        fetchMock.mockImplementation(async () => refused());
        await finish(6, { ph: 70 });

        expect(await firstFailed()).toEqual({ stop_id: 6, pool_name: 'Pool 6', error: 'The pH must be between 0 and 14.' });

        // It stays on the device, but a later sync doesn't send it again.
        fetchMock.mockClear();
        expect(await flushQueue()).toBe(1);
        expect(fetchMock).not.toHaveBeenCalled();
        expect(await failedCount()).toBe(1);
    });

    it('is sent again on Retry', async () => {
        fetchMock.mockImplementation(async () => refused());
        await finish(7, { ph: 70 });

        fetchMock.mockResolvedValue(json(200));
        expect(await retryFailed()).toBe(0);
    });

    it('can be reopened: it leaves the queue and comes back as the stop’s draft', async () => {
        fetchMock.mockImplementation(async () => refused());
        await finish(8, { ph: 70, notes: 'Gate was open', tasks: [{ name: 'Skim', done: true }] });

        await reopenFailed(8);

        expect(await allQueued()).toEqual([]);
        const { draft } = await getDraft(8);
        expect(draft).toMatchObject({ stop_id: 8, reading: { ph: '70' }, notes: 'Gate was open', tasks: [{ name: 'Skim', done: true }] });
    });

    it('can be discarded without touching other stops’ visits', async () => {
        fetchMock.mockImplementation(async (url) => (url.includes('/9/') ? refused() : offline()));
        await finish(9, { ph: 70 });
        await finish(10);

        await discardFailed(9);

        expect(await allQueued()).toMatchObject([{ stop_id: 10, status: 'pending' }]);
    });
});

describe('loading the day’s route', () => {
    const bundle = (date: string) => ({ date, generated_at: '', agent: { id: 1, name: 'Ashley' }, stops: [], inventory: [] }) as TodayBundle;

    it('saves the route, and shows the saved one with no signal', async () => {
        fetchMock.mockResolvedValue(json(200, bundle('2026-03-02')));
        expect(await loadToday('2026-03-02')).toMatchObject({ source: 'network' });

        fetchMock.mockImplementation(offline);
        expect(await loadToday('2026-03-02')).toMatchObject({ source: 'cache', bundle: { date: '2026-03-02' } });
        expect(await loadToday('2026-03-09')).toEqual({ bundle: null, source: 'none' });
    });

    it('never saves a route the office opened for a tech over the person’s own', async () => {
        fetchMock.mockResolvedValue(json(200, bundle('2026-04-06')));
        await loadToday('2026-04-06', 7);

        expect(await getBundle('2026-04-06')).toBeNull();
    });
});
