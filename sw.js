const CACHE = 'agenda-v2';
const ASSETS = [
    './',
    './index.html',
    './cadastrousuario.html',
    './download.html',
    './manifest.json',
    'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css'
];

self.addEventListener('install', function (e) {
    e.waitUntil(
        caches.open(CACHE).then(function (c) { return c.addAll(ASSETS); })
    );
    self.skipWaiting();
});

self.addEventListener('activate', function (e) {
    e.waitUntil(
        caches.keys().then(function (keys) {
            return Promise.all(keys.filter(function (k) { return k !== CACHE; }).map(function (k) { return caches.delete(k); }));
        })
    );
    self.clients.claim();
});

// Network first — sempre tenta buscar dado fresco; cai no cache só se offline
self.addEventListener('fetch', function (e) {
    if (e.request.method !== 'GET') return;
    e.respondWith(
        fetch(e.request).then(function (res) {
            var clone = res.clone();
            caches.open(CACHE).then(function (c) { c.put(e.request, clone); });
            return res;
        }).catch(function () {
            return caches.match(e.request);
        })
    );
});
