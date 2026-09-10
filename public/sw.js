const CACHE_NAME = 'cremona-pwa-v1';
const APP_ASSETS = [
    '/pwa/icons/cremona-192.png',
    '/pwa/icons/cremona-512.png',
    '/pwa/icons/cremona-180.png',
];

self.addEventListener('install', (event) => {
    event.waitUntil(caches.open(CACHE_NAME).then((cache) => cache.addAll(APP_ASSETS)));
    self.skipWaiting();
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys().then((keys) => Promise.all(
            keys.filter((key) => key !== CACHE_NAME).map((key) => caches.delete(key)),
        )),
    );
    self.clients.claim();
});

// Ne jamais mettre en cache les pages authentifiées ou les réponses Livewire :
// l'application doit toujours demander les données actuelles au serveur.
self.addEventListener('fetch', (event) => {
    const request = event.request;
    const url = new URL(request.url);

    if (request.method !== 'GET' || url.origin !== self.location.origin || !url.pathname.startsWith('/pwa/icons/')) {
        return;
    }

    event.respondWith(caches.match(request).then((cached) => cached || fetch(request)));
});
