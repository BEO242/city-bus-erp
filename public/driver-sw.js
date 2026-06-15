// City Bus Driver — Service Worker
const CACHE = 'cb-driver-v1';
const PRECACHE = [
  '/m/driver',
  '/assets/css/tailwind-built.css',
  '/driver-manifest.webmanifest',
];

self.addEventListener('install', (e) => {
  e.waitUntil(caches.open(CACHE).then((c) => c.addAll(PRECACHE)).catch(() => {}));
  self.skipWaiting();
});

self.addEventListener('activate', (e) => {
  e.waitUntil(
    caches.keys().then((keys) =>
      Promise.all(keys.filter((k) => k !== CACHE).map((k) => caches.delete(k)))
    )
  );
  self.clients.claim();
});

// Network-first pour HTML, cache-first pour assets
self.addEventListener('fetch', (e) => {
  const req = e.request;
  if (req.method !== 'GET') return;

  const url = new URL(req.url);
  const isAsset = /\.(css|js|png|svg|webp|ico|webmanifest)$/.test(url.pathname);

  if (isAsset) {
    e.respondWith(
      caches.match(req).then((hit) =>
        hit || fetch(req).then((resp) => {
          const copy = resp.clone();
          caches.open(CACHE).then((c) => c.put(req, copy));
          return resp;
        }).catch(() => hit)
      )
    );
  } else if (url.pathname.startsWith('/m/driver')) {
    e.respondWith(
      fetch(req).then((resp) => {
        const copy = resp.clone();
        caches.open(CACHE).then((c) => c.put(req, copy));
        return resp;
      }).catch(() => caches.match(req).then((hit) => hit || caches.match('/m/driver')))
    );
  }
});

// Sync queue offline (postMessage from page)
self.addEventListener('message', (e) => {
  if (e.data && e.data.type === 'CLEAR_CACHE') {
    caches.keys().then((keys) => keys.forEach((k) => caches.delete(k)));
  }
});
