/**
 * RoutePilot service worker — installable PWA shell + offline resilience.
 *
 * Strategy:
 *   - Navigations (HTML)  → network-first, fall back to the last-cached page,
 *     then to /offline.html. Keeps deploys fresh online; survives going offline.
 *   - Built assets (/build/, hashed + immutable) → cache-first.
 *   - Other same-origin GETs (icons, manifest) → stale-while-revalidate.
 *   - API + non-GET + cross-origin → never touched (let them hit the network;
 *     the field app's offline queue handles mutations in a later slice).
 *
 * Bump CACHE_VERSION to invalidate all caches on the next deploy.
 */
const CACHE_VERSION = 'v1';
const SHELL_CACHE = `rp-shell-${CACHE_VERSION}`;
const ASSET_CACHE = `rp-assets-${CACHE_VERSION}`;
const PAGE_CACHE = `rp-pages-${CACHE_VERSION}`;
const OFFLINE_URL = '/offline.html';
const PRECACHE = [OFFLINE_URL, '/assets/images/pwa/icon-192.png', '/assets/images/pwa/icon-512.png'];

self.addEventListener('install', (event) => {
    event.waitUntil(caches.open(SHELL_CACHE).then((cache) => cache.addAll(PRECACHE)));
    self.skipWaiting();
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches
            .keys()
            .then((keys) =>
                Promise.all(keys.filter((k) => !k.endsWith(CACHE_VERSION)).map((k) => caches.delete(k))),
            )
            .then(() => self.clients.claim()),
    );
});

/** Cache-first: serve from cache, otherwise fetch + store. For immutable assets. */
async function cacheFirst(request, cacheName) {
    const cached = await caches.match(request);
    if (cached) return cached;
    const response = await fetch(request);
    if (response.ok) {
        const cache = await caches.open(cacheName);
        cache.put(request, response.clone());
    }
    return response;
}

/** Stale-while-revalidate: serve cache immediately, refresh in the background. */
async function staleWhileRevalidate(request, cacheName) {
    const cache = await caches.open(cacheName);
    const cached = await cache.match(request);
    const network = fetch(request)
        .then((response) => {
            if (response.ok) cache.put(request, response.clone());
            return response;
        })
        .catch(() => cached);
    return cached || network;
}

/** Network-first for navigations: fresh when online, last-seen page or offline shell when not. */
async function navigate(request) {
    const cache = await caches.open(PAGE_CACHE);
    try {
        const response = await fetch(request);
        if (response.ok) cache.put(request, response.clone());
        return response;
    } catch {
        const cached = await cache.match(request);
        return cached || (await caches.match(OFFLINE_URL));
    }
}

self.addEventListener('fetch', (event) => {
    const { request } = event;
    const url = new URL(request.url);

    // Only same-origin GETs are eligible; never intercept the API or mutations.
    if (request.method !== 'GET' || url.origin !== self.location.origin) return;
    if (url.pathname.startsWith('/api/')) return;

    if (request.mode === 'navigate') {
        event.respondWith(navigate(request));
        return;
    }
    if (url.pathname.startsWith('/build/')) {
        event.respondWith(cacheFirst(request, ASSET_CACHE));
        return;
    }
    if (url.pathname.startsWith('/assets/')) {
        event.respondWith(staleWhileRevalidate(request, ASSET_CACHE));
    }
});
