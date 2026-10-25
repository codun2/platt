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
  const register = () => navigator.serviceWorker.register(`/app/sw.js?v=${build}`, { scope: '/app/' }).catch(() => { /* unsupported / blocked */ })
  // The SPA may boot after the window "load" event has already fired.
  document.readyState === 'complete' ? register() : window.addEventListener('load', register, { once: true })
})
