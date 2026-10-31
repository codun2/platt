/**
 * Recover from a stale build after a deploy. The device's service worker may still
 * serve the previous build, so a dynamically-imported screen chunk 404s
 * ("Importing a module script failed"). A plain reload would just hit the same stale
 * SW and loop → the 500 page. So we BUST the SW caches first, then reload once.
 */
export default defineNuxtPlugin((nuxtApp) => {
  const KEY = 'rakeez_chunk_reload'

  async function bustAndReload(to?: string) {
    // Online only: clearing caches offline would wipe the app the user is relying on.
    if (!navigator.onLine || (useOffline?.().state?.online === false)) {
      const saved = new URL(navigator.serviceWorker?.controller?.scriptURL || location.href).searchParams.get('v')
      const build = (useRuntimeConfig().app as any).buildId || 'dev'
      if (to && saved && saved !== build) { window.location.href = to; return }
      try { uiAlert(tt('هذه الشاشة غير محفوظة على الجهاز بعد لأن التطبيق تحدّث. اتصل بالإنترنت وأعد المحاولة.', 'This screen is not saved yet because the app updated. Reconnect and retry.'), { tone: 'warn' }) } catch { /* */ }
      return
    }
    // Loop guard: one recovery per 20s.
    let last = 0
    try { last = +(sessionStorage.getItem(KEY) || 0) } catch { /* */ }
    if (Date.now() - last < 20000) return
    try { sessionStorage.setItem(KEY, String(Date.now())) } catch { /* */ }
    // Clear the SW caches so the reload fetches the fresh build from the network,
    // and nudge the service worker to update. The SW stays registered (offline still works once it re-caches).
    try { const ks = await caches.keys(); await Promise.all(ks.map(k => caches.delete(k))) } catch { /* */ }
    try { const regs = await navigator.serviceWorker?.getRegistrations?.(); await Promise.all((regs || []).map(r => r.update().catch(() => {}))) } catch { /* */ }
    to ? (window.location.href = to) : window.location.reload()
  }

  window.addEventListener('vite:preloadError', (e) => { e.preventDefault(); bustAndReload() })
  nuxtApp.hook('app:chunkError', () => bustAndReload())
  useRouter().onError((err, to) => {
    if (/dynamically imported module|Importing a module script failed|error loading dynamically|Failed to fetch dynamically/i.test(String(err?.message || err))) {
      bustAndReload(to?.fullPath ? useRuntimeConfig().app.baseURL.replace(/\/$/, '') + to.fullPath : undefined)
    }
  })
})
