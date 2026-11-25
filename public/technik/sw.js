// Service worker – aplikace funguje i bez signálu.
// Soubory aplikace: síť napřed (vždy aktuální verze), při výpadku z cache.
// API se tu necachuje – data drží aplikace v IndexedDB.
const CACHE = 'pap-technik-v1';
const SHELL = [
  './', 'index.html', 'manifest.webmanifest', 'css/app.css',
  'js/app.js', 'js/api.js', 'js/db.js', 'js/sync.js', 'js/i18n.js', 'js/ui.js', 'js/photo.js',
  'icons/icon-192.png', 'icons/icon-512.png', 'icons/apple-touch-icon.png',
];

self.addEventListener('install', (event) => {
  event.waitUntil(caches.open(CACHE).then((c) => c.addAll(SHELL)).then(() => self.skipWaiting()));
});

self.addEventListener('activate', (event) => {
  event.waitUntil(
    caches.keys()
      .then((keys) => Promise.all(keys.filter((k) => k !== CACHE).map((k) => caches.delete(k))))
      .then(() => self.clients.claim())
  );
});

self.addEventListener('fetch', (event) => {
  const req = event.request;
  const url = new URL(req.url);
  if (req.method !== 'GET' || url.origin !== self.location.origin || !url.pathname.startsWith('/technik/')) {
    return; // API a cizí požadavky jdou rovnou na síť
  }

  event.respondWith((async () => {
    const cache = await caches.open(CACHE);
    try {
      const fresh = await fetch(req, { cache: 'no-cache' });
      if (fresh.ok) cache.put(req, fresh.clone());
      return fresh;
    } catch (e) {
      const hit = await cache.match(req, { ignoreSearch: true });
      if (hit) return hit;
      if (req.mode === 'navigate') return cache.match('index.html');
      throw e;
    }
  })());
});
