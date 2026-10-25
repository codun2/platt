/*
 * ركائز — Service Worker (scope /app/).
 * - App files (/app/_nuxt/*, hashed) → cache-first; the full list is precached from
 *   precache.json at install, so every screen opens offline, not only visited ones.
 * - Navigations → network-first, falling back to the cached app shell (SPA).
 * - API reads (GET /api/app/*, /api/auth/me) → network-first with the last copy as
 *   fallback (marked X-Rk-Cache: fallback). Writes are never intercepted: the app's
 *   own outbox (IndexedDB) queues them while offline.
 * Requires a secure context (HTTPS or localhost).
 */
// Registered as sw.js?v=<buildId>: each deploy installs a fresh worker and cache set.
const VERSION = 'rk-' + (new URL(self.location.href).searchParams.get('v') || 'dev');
const SHELL = `${VERSION}-shell`, ASSETS = `${VERSION}-assets`, API = `${VERSION}-api`, FONTS = `${VERSION}-fonts`;
const KEEP = [SHELL, ASSETS, API, FONTS];
const BASE = '/app/';

self.addEventListener('install', (e) => {
  e.waitUntil((async () => {
    const shell = await caches.open(SHELL);
    await shell.add(BASE).catch(() => {});
    try {
      const list = await (await fetch(`${BASE}precache.json`, { cache: 'no-store' })).json();
      const assets = await caches.open(ASSETS);
      // one by one so a single missing file never aborts the install
      await Promise.all(list.map((u) => assets.add(u).catch(() => {})));
    } catch { /* precache list missing: assets are still cached on first use */ }
    await self.skipWaiting();
  })());
});

self.addEventListener('activate', (e) => {
  e.waitUntil((async () => {
    for (const k of await caches.keys()) if (!KEEP.includes(k)) await caches.delete(k);
    await self.clients.claim();
  })());
});

let apiPausedUntil = 0;
self.addEventListener('message', (e) => {
  // Logout: never leak one account's data to the next one on the same device.
  if (e.data === 'CLEAR_API') { apiPausedUntil = Date.now() + 5000; e.waitUntil(caches.delete(API)); }
});

self.addEventListener('fetch', (e) => {
  const req = e.request;
  if (req.method !== 'GET') return;
  const url = new URL(req.url);

  if (url.origin !== self.location.origin) {
    if (/fonts\.(googleapis|gstatic)\.com$|cdn\.jsdelivr\.net$/.test(url.hostname)) e.respondWith(staleWhileRevalidate(req, FONTS));
    return;
  }
  if (url.pathname.startsWith('/api/')) {
    const cacheable = url.pathname.startsWith('/api/app/') || url.pathname === '/api/auth/me';
    if (cacheable) e.respondWith(networkFirst(req, API));
    return;
  }
  if (!url.pathname.startsWith(BASE)) return;
  if (url.pathname.startsWith(`${BASE}_nuxt/`)) { e.respondWith(cacheFirst(req, ASSETS)); return; }
  if (req.mode === 'navigate') { e.respondWith(networkFirst(req, SHELL, BASE)); return; }
  e.respondWith(networkFirst(req, SHELL));
});

async function cacheFirst(req, name) {
  const c = await caches.open(name);
  const hit = await c.match(req);
  if (hit) return hit;
  const res = await fetch(req);
  if (res.ok) c.put(req, res.clone()).catch(() => {});
  return res;
}

async function networkFirst(req, name, fallbackPath) {
  try {
    const res = await fetch(req);
    if (res && res.ok && !(name === API && Date.now() < apiPausedUntil)) (await caches.open(name)).put(req, res.clone()).catch(() => {});
    return res;
  } catch (err) {
    const c = await caches.open(name);
    const hit = (await c.match(req, { ignoreVary: true })) || (fallbackPath ? await c.match(fallbackPath) : null);
    if (hit) {
      if (name !== API) return hit;
      const h = new Headers(hit.headers); h.set('X-Rk-Cache', 'fallback');
      return new Response(hit.body, { status: hit.status, statusText: hit.statusText, headers: h });
    }
    if (name === API) {
      return new Response(JSON.stringify({ offline: true, message: 'أنت غير متصل بالإنترنت ولا توجد نسخة محفوظة من هذه البيانات على الجهاز.' }), { status: 503, headers: { 'Content-Type': 'application/json; charset=utf-8', 'X-Rk-Cache': 'miss' } });
    }
    throw err;
  }
}

async function staleWhileRevalidate(req, name) {
  const c = await caches.open(name);
  const cached = await c.match(req);
  const net = fetch(req).then((r) => { if (r && (r.ok || r.type === 'opaque')) c.put(req, r.clone()).catch(() => {}); return r; }).catch(() => null);
  return cached || (await net) || Response.error();
}
