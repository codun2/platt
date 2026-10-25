/**
 * After a deploy, a page that was already open still runs the previous build and
 * asks for screen files that no longer exist ("Failed to fetch dynamically imported
 * module"). Reload once onto the new build instead of showing an error page.
 * Guarded so a real outage (offline, files truly missing) never loops.
 */
export default defineNuxtPlugin((nuxtApp) => {
  const KEY = 'rakeez_chunk_reload'
  const reload = (to?: string) => {
    // Offline: this screen's files are not on the device (the app updated meanwhile). Say so instead of doing nothing.
    if (!navigator.onLine || !useOffline().state.online) {
      // Offline: open the screen as a full page from the device's saved copy first.
      const saved = new URL(navigator.serviceWorker?.controller?.scriptURL || location.href).searchParams.get('v')
      if (to && saved && saved !== ((useRuntimeConfig().app as any).buildId || 'dev')) { window.location.href = to; return }
      uiAlert(tt('هذه الشاشة غير محفوظة على الجهاز بعد لأن التطبيق تحدّث. اتصل بالإنترنت مرة واحدة ثم أعد فتح التطبيق، وبعدها تعمل كل الشاشات دون اتصال.', 'This screen is not saved on the device yet because the app was updated. Connect once and reopen the app; then every screen works offline.'), { tone: 'warn' })
      return
    }
    let last = 0
    try { last = +(sessionStorage.getItem(KEY) || 0) } catch { /* storage blocked */ }
    if (Date.now() - last < 15000) return
    try { sessionStorage.setItem(KEY, String(Date.now())) } catch { /* storage blocked */ }
    to ? (window.location.href = to) : window.location.reload()
  }
  window.addEventListener('vite:preloadError', (e) => { e.preventDefault(); reload() })
  nuxtApp.hook('app:chunkError', () => reload())
  // Navigation that failed on a missing file: open the target page fresh.
  useRouter().onError((err, to) => {
    if (/dynamically imported module|Importing a module script failed|error loading dynamically/i.test(String(err?.message || err))) reload(to?.fullPath ? useRuntimeConfig().app.baseURL.replace(/\/$/, '') + to.fullPath : undefined)
  })
})
