const VERSION = 'sarak-v12';
const API_MAX_AGE_MS = 5 * 60 * 1000;

// '/' is deliberately NOT precached. It is a server-rendered shell, so caching
// it meant a deploy could never reach a returning visitor. Everything below
// that ships in public/ is static, so those stay cache-first.
const PRECACHE = [
    '/manifest.webmanifest',
    '/icons/sarak-mark.svg',
    '/icons/atlas-mark.svg'
];

self.addEventListener('install', (event) => {
    event.waitUntil(
        caches.open(VERSION)
            .then((cache) => cache.addAll(PRECACHE))
            .then(() => self.skipWaiting())
    );
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys()
            .then((keys) => Promise.all(keys.filter((key) => key !== VERSION).map((key) => caches.delete(key))))
            .then(() => self.clients.claim())
    );
});

function stamp(response) {
    return response.blob().then((blob) => {
        const headers = new Headers(response.headers);
        headers.set('x-sw-cached-at', String(Date.now()));
        return new Response(blob, {
            status: response.status,
            statusText: response.statusText,
            headers: headers
        });
    });
}

function isFresh(response, maxAge) {
    if (!response) return false;
    const at = Number(response.headers.get('x-sw-cached-at'));
    return Number.isFinite(at) && (Date.now() - at) < maxAge;
}

self.addEventListener('fetch', (event) => {
    const request = event.request;
    if (request.method !== 'GET') return;

    const url = new URL(request.url);
    if (url.origin !== self.location.origin) return;

    const path = url.pathname;

    // Basemap tiles: content-addressed by style/zoom/x/y and never change.
    // Cache-first means a revisit never touches PHP or the Carto CDN.
    if (path.startsWith('/tiles/')) {
        event.respondWith(
            caches.match(request).then((cached) => {
                if (cached) return cached;
                return fetch(request).then((response) => {
                    if (response.ok) {
                        const copy = response.clone();
                        caches.open(VERSION).then((cache) => cache.put(request, copy));
                    }
                    return response;
                });
            }).catch(() => caches.match(request))
        );
        return;
    }

    // API data: network-first, but only fall back to cache while it is fresh.
    // Previously every successful response was stored with no TTL, so a stale
    // place list could be served indefinitely.
    if (path.startsWith('/api/')) {
        event.respondWith(
            fetch(request).then((response) => {
                if (response.ok) {
                    const copy = response.clone();
                    caches.open(VERSION)
                        .then((cache) => stamp(response).then((stamped) => cache.put(request, stamped)));
                }
                return response;
            }).catch(() => caches.match(request).then((cached) => {
                if (isFresh(cached, API_MAX_AGE_MS)) return cached;
                return new Response(
                    JSON.stringify({ message: 'Service temporarily unavailable. Please retry.' }),
                    { status: 503, headers: { 'Content-Type': 'application/json' } }
                );
            }))
        );
        return;
    }

    // Vite build assets and icons: fingerprinted or static, so cache-first.
    if (path.startsWith('/build/') || path.startsWith('/icons/')
        || path === '/manifest.webmanifest' || path === '/favicon.ico') {
        event.respondWith(
            caches.match(request).then((cached) => cached || fetch(request).then((response) => {
                if (response.ok) {
                    const copy = response.clone();
                    caches.open(VERSION).then((cache) => cache.put(request, copy));
                }
                return response;
            }))
        );
    }
});
