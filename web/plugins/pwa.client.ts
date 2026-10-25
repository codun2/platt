/**
 * Registers the service worker (needs HTTPS or localhost). Versioned by the build
 * id, so each deploy installs fresh app files. On plain HTTP the app still works
 * offline while open (IndexedDB outbox); only opening it from scratch needs the SW.
 */
export default defineNuxtPlugin(() => {
  // "Install app" button (TopBar): keep the browser's prompt for later.
  window.addEventListener('beforeinstallprompt', (e) => { e.preventDefault(); setInstallPrompt(e) })
  window.addEventListener('appinstalled', () => markInstalled())
  if (!('serviceWorker' in navigator) || !window.isSecureContext || import.meta.dev) return
  const build = (useRuntimeConfig().app as any).buildId || 'dev'
  const register = () => navigator.serviceWorker.register(`/app/sw.js?v=${build}`, { scope: '/app/' })
    .then(() => navigator.serviceWorker.ready)
    .then(reg => { if (navigator.onLine) reg.active?.postMessage('FILL') })
    .catch(() => { /* unsupported / blocked */ })

  // This page and the device's saved copy can be different versions (an update
  // arrived while the app stayed open). Offline, the next screen opened is then
  // loaded as a full page from the saved copy, whose files are all on the device.
  const savedBuild = () => new URL(navigator.serviceWorker.controller?.scriptURL || location.href).searchParams.get('v')
  useRouter().beforeEach((to) => {
    const offline = !navigator.onLine || !useOffline().state.online
    const saved = savedBuild()
    if (offline && saved && saved !== build) {
      window.location.href = useRuntimeConfig().app.baseURL.replace(/\/$/, '') + to.fullPath
      return false
    }
  })
  // The SPA may boot after the window "load" event has already fired.
  document.readyState === 'complete' ? register() : window.addEventListener('load', register, { once: true })
})
