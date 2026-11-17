const CACHE_NAME = 'kalinga-pwa-v1';
const OFFLINE_URL = './offline.html';

const PRECACHE_ASSETS = [
    './manifest.json',
    './offline.html',
    './images/logo.png',
    './images/pwa/icon-192x192.png',
    './images/pwa/icon-512x512.png',
    './images/pwa/apple-touch-icon.png'
];

self.addEventListener('install', (event) => {
    event.waitUntil(
        caches.open(CACHE_NAME).then((cache) => {
            return cache.addAll(PRECACHE_ASSETS).catch(() => {});
        }).then(() => self.skipWaiting())
    );
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys().then((keys) => {
            return Promise.all(
                keys.map((key) => {
                    if (key !== CACHE_NAME) return caches.delete(key);
                })
            );
        }).then(() => self.clients.claim())
    );
});

self.addEventListener('fetch', (event) => {
    const req = event.request;
    if (req.method !== 'GET') return;

    const url = new URL(req.url);

    // Dynamic offline caching for slips and stubs
    const isDoc = url.pathname.includes('appointment-slip.php') || url.pathname.includes('print-claim-stub.php');

    if (isDoc) {
        event.respondWith(
            fetch(req).then((res) => {
                if (res && res.status === 200) {
                    const clone = res.clone();
                    caches.open(CACHE_NAME).then((c) => c.put(req, clone));
                }
                return res;
            }).catch(() => {
                return caches.match(req).then((cached) => cached || caches.match(OFFLINE_URL));
            })
        );
        return;
    }

    // Navigation requests
    if (req.mode === 'navigate' || req.destination === 'document') {
        event.respondWith(
            fetch(req).then((res) => {
                if (res && res.status === 200) {
                    const clone = res.clone();
                    caches.open(CACHE_NAME).then((c) => c.put(req, clone));
                }
                return res;
            }).catch(() => {
                return caches.match(req).then((cached) => cached || caches.match(OFFLINE_URL));
            })
        );
        return;
    }

    // Static assets
    event.respondWith(
        caches.match(req).then((cached) => {
            return cached || fetch(req).then((res) => {
                if (res && res.status === 200) {
                    const clone = res.clone();
                    caches.open(CACHE_NAME).then((c) => c.put(req, clone));
                }
                return res;
            });
        }).catch(() => {})
    );
});
