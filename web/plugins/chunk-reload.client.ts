/**
 * After a deploy, a page that was already open still runs the previous build and
 * asks for screen files that no longer exist ("Failed to fetch dynamically imported
 * module"). Reload once onto the new build instead of showing an error page.
 * Guarded so a real outage (offline, files truly missing) never loops.
 */
export default defineNuxtPlugin((nuxtApp) => {
  const KEY = 'rakeez_chunk_reload'
  const reload = (to?: string) => {
    if (!navigator.onLine) return
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
