const CACHE_NAME = 'marcelinos-static-v2';
const ASSETS_TO_CACHE = [
    '/manifest.webmanifest',
    '/icons/icon-192.png',
    '/icons/icon-512.png',
    '/icons/apple-touch-icon.png',
];
const STATIC_FILE_EXTENSIONS = [
    '.css',
    '.js',
    '.png',
    '.jpg',
    '.jpeg',
    '.webp',
    '.svg',
    '.ico',
    '.woff',
    '.woff2',
    '.ttf',
];

self.addEventListener('install', (event) => {
    event.waitUntil(
        caches.open(CACHE_NAME).then((cache) => cache.addAll(ASSETS_TO_CACHE)),
    );
    self.skipWaiting();
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys().then((keys) =>
            Promise.all(
                keys
                    .filter((key) => key !== CACHE_NAME)
                    .map((key) => caches.delete(key)),
            ),
        ),
    );
    self.clients.claim();
});

self.addEventListener('fetch', (event) => {
    if (event.request.method !== 'GET') {
        return;
    }

    const requestUrl = new URL(event.request.url);

    // Do not intercept cross-origin requests or document navigations.
    // This keeps admin/staff routes network-driven and avoids stale/offline shells.
    if (requestUrl.origin !== self.location.origin || event.request.mode === 'navigate') {
        return;
    }

    const isStaticAsset = STATIC_FILE_EXTENSIONS.some((ext) =>
        requestUrl.pathname.endsWith(ext),
    );
    const isManifest = requestUrl.pathname.endsWith('/manifest.webmanifest');

    if (!isStaticAsset && !isManifest) {
        return;
    }

    event.respondWith(
        caches.match(event.request).then((cachedResponse) => {
            if (cachedResponse) {
                return cachedResponse;
            }

            return fetch(event.request)
                .then((networkResponse) => {
                    const responseClone = networkResponse.clone();
                    caches
                        .open(CACHE_NAME)
                        .then((cache) => cache.put(event.request, responseClone));

                    return networkResponse;
                })
                .catch(() => {
                    return new Response('', {
                        status: 503,
                        statusText: 'Service Unavailable',
                    });
                });
        }),
    );
});
