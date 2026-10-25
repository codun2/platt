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
const BUILD = new URL(self.location.href).searchParams.get('v') || 'dev';
const VERSION = 'rk-' + BUILD;
const SHELL = `${VERSION}-shell`, ASSETS = `${VERSION}-assets`, API = `${VERSION}-api`, FONTS = `${VERSION}-fonts`;
const KEEP = [SHELL, ASSETS, API, FONTS];
const BASE = '/app/';

self.addEventListener('install', (e) => {
  e.waitUntil((async () => {
    // The app page is saved ONCE, here, from the same deploy as this worker's file
    // list — so the offline page and its screen files always belong together.
    const shell = await caches.open(SHELL);
    try {
      const page = await fetch(BASE, { cache: 'no-store' });
      const html = await page.clone().text();
      if (page.ok && html.includes(BUILD)) await shell.put(BASE, page);
    } catch { /* offline during install: the next online visit installs again */ }
    try {
      const list = await (await fetch(`${BASE}precache.json`, { cache: 'no-store' })).json();
      // The app's "ready offline" indicator compares the device copy against this list.
      await (await caches.open('rk-meta')).put(`precache-${VERSION}`, new Response(JSON.stringify(list)));
      const assets = await caches.open(ASSETS);
      // one by one so a single missing file never aborts the install
      await Promise.all(list.map((u) => assets.add(u).catch(() => {})));
    } catch { /* precache list missing: assets are still cached on first use */ }
    await self.skipWaiting();
  })());
});

self.addEventListener('activate', (e) => {
  e.waitUntil((async () => {
    // Keep the previous build's app files too: a page still open on it can finish
    // loading its screens (and reload onto the new build) even when offline.
    const meta = await caches.open('rk-meta');
    const prev = await (await meta.match('versions'))?.json().catch(() => []) || [];
    const versions = [VERSION, ...prev.filter((v) => v !== VERSION)].slice(0, 2);
    await meta.put('versions', new Response(JSON.stringify(versions)));
    const keep = [...KEEP, 'rk-meta', ...versions.slice(1).map((v) => `${v}-assets`)];
    for (const r of await meta.keys()) if (/precache-/.test(r.url) && !r.url.endsWith(`precache-${VERSION}`)) await meta.delete(r);
    for (const k of await caches.keys()) if (!keep.includes(k)) await caches.delete(k);
    await self.clients.claim();
  })());
});

let apiPausedUntil = 0;
self.addEventListener('message', (e) => {
  // Logout: never leak one account's data to the next one on the same device.
  if (e.data === 'CLEAR_API') { apiPausedUntil = Date.now() + 5000; e.waitUntil(caches.delete(API)); }
  // Online app start: fetch any screen file of this version that is still missing
  // (an interrupted install, a file that failed) so the device copy is complete.
  if (e.data === 'FILL') e.waitUntil(fill());
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
  if (req.mode === 'navigate') { e.respondWith(navigate(req)); return; }
  e.respondWith(networkFirst(req, SHELL));
});

// Pages: the live one when online (never stored: a newer deploy's page would not
// match this worker's saved files), else this worker's own saved app page.
async function navigate(req) {
  try {
    const res = await fetch(req);
    if (res.status >= 502 && res.status <= 504) throw new Error('gateway');
    return res;
  } catch (err) {
    const hit = await (await caches.open(SHELL)).match(BASE);
    if (hit) return hit;
    throw err;
  }
}

async function cacheFirst(req, name) {
  const c = await caches.open(name);
  const hit = (await c.match(req)) || (await caches.match(req));   // also the previous build's files
  if (hit) return hit;
  const res = await fetch(req);
  if (res.ok) c.put(req, res.clone()).catch(() => {});
  return res;
}

async function networkFirst(req, name, fallbackPath) {
  try {
    const res = await fetch(req);
    // Gateway errors (the server behind the proxy is down) count as offline.
    if (res && res.status >= 502 && res.status <= 504) throw new Error('gateway');
    if (res && res.ok && !(name === API && Date.now() < apiPausedUntil)) (await caches.open(name)).put(req, res.clone()).catch(() => {});
    return res;
  } catch (err) {
    const c = await caches.open(name);
    const hit = (await c.match(req, { ignoreVary: true })) || (name === API ? await nearest(c, req) : null) || (fallbackPath ? await c.match(fallbackPath) : null);
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

// No copy of this exact URL (other page / filter): use the closest saved copy of the
// same list, preferring the one with the fewest query parameters.
async function nearest(c, req) {
  const all = await c.matchAll(req, { ignoreSearch: true, ignoreVary: true });
  if (!all.length) return null;
  return all.sort((a, b) => (a.url || '').length - (b.url || '').length)[0];
}

async function staleWhileRevalidate(req, name) {
  const c = await caches.open(name);
  const cached = await c.match(req);
  const net = fetch(req).then((r) => { if (r && (r.ok || r.type === 'opaque')) c.put(req, r.clone()).catch(() => {}); return r; }).catch(() => null);
  return cached || (await net) || Response.error();
}

async function fill() {
  const meta = await caches.open('rk-meta');
  let list = await (await meta.match(`precache-${VERSION}`))?.json().catch(() => null);
  if (!list) {
    try { list = await (await fetch(`${BASE}precache.json`, { cache: 'no-store' })).json(); await meta.put(`precache-${VERSION}`, new Response(JSON.stringify(list))); } catch { return; }
  }
  const assets = await caches.open(ASSETS);
  for (const u of list) if (!(await assets.match(u))) await assets.add(u).catch(() => {});
  const shell = await caches.open(SHELL);
  if (!(await shell.match(BASE))) {
    try { const page = await fetch(BASE, { cache: 'no-store' }); if (page.ok && (await page.clone().text()).includes(BUILD)) await shell.put(BASE, page); } catch { /* offline */ }
  }
}
