/**
 * Lightweight i18n for the Rakeez SPA (ssr:false).
 *
 *  - `locale` is a module-level singleton ref (safe: no SSR / cross-request state).
 *  - `t('a.b.c')` looks the key up in the active locale, falling back to Arabic
 *    then to the key itself. Supports `{name}` interpolation via a second arg.
 *  - `tt(ar, en)` returns the right inline string for ad-hoc bilingual bits.
 *  - `L(ar, en, extra)` builds a label-map value whose `.label` is locale-aware
 *    (used by status/label maps in other composables, so pages need no change).
 *  - `dir` / `setLocale` drive <html dir|lang>; persisted in localStorage.
 */
import { messages } from '~/i18n/messages'

export type Locale = 'ar' | 'en'

const STORAGE_KEY = 'rakeez_locale'
const _locale = ref<Locale>('ar')
let _inited = false

function initLocale() {
  if (_inited || !import.meta.client) return
  _inited = true
  try {
    const saved = localStorage.getItem(STORAGE_KEY) as Locale | null
    if (saved === 'ar' || saved === 'en') _locale.value = saved
  } catch { /* private mode */ }
  applyDir()
}

function applyDir() {
  if (!import.meta.client) return
  const el = document.documentElement
  el.setAttribute('lang', _locale.value)
  el.setAttribute('dir', _locale.value === 'ar' ? 'rtl' : 'ltr')
}

/** Current locale without the full composable (for label maps). */
export const currentLocale = () => _locale.value

/** Inline bilingual string. */
export const tt = (ar: string, en: string) => (_locale.value === 'en' ? en : ar)

/**
 * Label-map value with a reactive `.label` (and `cls`/extra fields). Also
 * stringifies to the localized label, so both `obj.label` and `{{ obj }}`
 * (pure-string maps) localize with no call-site changes.
 */
export const L = <T extends Record<string, any>>(ar: string, en: string, extra?: T) => {
  const pick = () => (_locale.value === 'en' ? en : ar)
  return { ...(extra as T), get label() { return pick() }, toString: pick, toJSON: pick }
}

function lookup(key: string, loc: Locale): string | undefined {
  const parts = key.split('.')
  let node: any = messages[loc]
  for (const p of parts) { node = node?.[p]; if (node == null) return undefined }
  return typeof node === 'string' ? node : undefined
}

export function t(key: string, params?: Record<string, string | number>): string {
  let out = lookup(key, _locale.value) ?? (_locale.value === 'en' ? lookup(key, 'ar') : undefined) ?? key
  if (params) for (const [k, v] of Object.entries(params)) out = out.replace(new RegExp(`\\{${k}\\}`, 'g'), String(v))
  return out
}

export function useI18n() {
  initLocale()
  function setLocale(l: Locale) {
    _locale.value = l
    if (import.meta.client) { try { localStorage.setItem(STORAGE_KEY, l) } catch {} }
    applyDir()
  }
  function toggle() { setLocale(_locale.value === 'ar' ? 'en' : 'ar') }
  return {
    locale: _locale,
    isEn: computed(() => _locale.value === 'en'),
    dir: computed(() => (_locale.value === 'ar' ? 'rtl' : 'ltr')),
    t,
    tt,
    setLocale,
    toggle,
  }
}
