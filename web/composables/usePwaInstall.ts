/**
 * "Install app" support. Android / desktop Chrome & Edge fire `beforeinstallprompt`
 * (captured in plugins/pwa.client.ts); iPhone Safari has no prompt, so it gets
 * short instructions instead (Share → Add to Home Screen).
 */
const _prompt = ref<any>(null)
const _installed = ref(false)

export const setInstallPrompt = (e: any) => { _prompt.value = e }
export const markInstalled = () => { _installed.value = true; _prompt.value = null }

export function usePwaInstall() {
  const standalone = () => window.matchMedia?.('(display-mode: standalone)').matches || (navigator as any).standalone === true
  const isIos = () => /iphone|ipad|ipod/i.test(navigator.userAgent) || (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1)
  const canInstall = computed(() => !_installed.value && !standalone() && (!!_prompt.value || isIos()))
  async function install() {
    if (_prompt.value) {
      _prompt.value.prompt()
      const r = await _prompt.value.userChoice.catch(() => null)
      if (r?.outcome === 'accepted') markInstalled()
      _prompt.value = null
      return
    }
    uiAlert(tt('على الآيفون/الآيباد: افتح الموقع في Safari ← اضغط زر المشاركة (المربع والسهم) ← «إضافة إلى الشاشة الرئيسية».', 'On iPhone/iPad: open the site in Safari → tap Share → “Add to Home Screen”.'), { tone: 'info', title: tt('تثبيت ركائز', 'Install Rakeez') })
  }
  return { canInstall, install }
}
