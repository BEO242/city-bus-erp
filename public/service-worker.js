/* ============================================================
   City Bus — Service Worker (PWA + offline)
   ============================================================ */
const CACHE_VERSION = 'citybus-v3-2026-05-09';
const CORE_ASSETS = [
  'assets/css/app.css',
  'assets/js/app.js',
  'assets/js/qr-scanner.js',
  'assets/js/offline.js',
  'assets/manifest.json',
  'assets/img/favicon.svg'
];

self.addEventListener('install', e => {
  e.waitUntil(
    caches.open(CACHE_VERSION).then(c => c.addAll(CORE_ASSETS.map(a => new Request(a, { cache: 'reload' }))))
      .catch(() => null)
  );
  self.skipWaiting();
});

self.addEventListener('activate', e => {
  e.waitUntil(
    caches.keys().then(keys => Promise.all(
      keys.filter(k => k !== CACHE_VERSION).map(k => caches.delete(k))
    ))
  );
  self.clients.claim();
});

self.addEventListener('fetch', e => {
  const req = e.request;
  if (req.method !== 'GET') return;

  const url = new URL(req.url);
  const isAsset = /\.(css|js|svg|png|jpg|woff2?|ico)(\?.*)?$/.test(url.pathname);
  const isHTML = req.headers.get('accept')?.includes('text/html');

  if (isAsset) {
    // cache-first pour les assets
    e.respondWith(
      caches.match(req).then(cached => cached || fetch(req).then(res => {
        if (res.ok) {
          const copy = res.clone();
          caches.open(CACHE_VERSION).then(c => c.put(req, copy));
        }
        return res;
      }))
    );
  } else if (isHTML) {
    // network-first pour le HTML, fallback offline
    e.respondWith(
      fetch(req).then(res => {
        const copy = res.clone();
        caches.open(CACHE_VERSION).then(c => c.put(req, copy));
        return res;
      }).catch(() => caches.match(req).then(c => c || new Response(
        '<h1>Hors-ligne</h1><p>Cette page nécessite une connexion.</p>',
        { headers: { 'Content-Type': 'text/html; charset=utf-8' } }
      )))
    );
  }
});
