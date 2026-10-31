// Light / dark theme. The chosen mode is written to <html data-theme> and
// persisted in localStorage. An inline head script (nuxt.config) sets the
// attribute before paint to avoid a flash; this composable keeps the reactive
// state in sync and flips it at runtime.
export type ThemeMode = 'light' | 'dark'

export const useTheme = () => {
  const mode = useState<ThemeMode>('theme', () => 'light')

  const set = (m: ThemeMode) => {
    mode.value = m
    if (import.meta.client) {
      document.documentElement.dataset.theme = m
      try { localStorage.setItem('rk_theme', m) } catch {}
    }
  }

  const init = () => {
    if (!import.meta.client) return
    let saved: string | null = null
    try { saved = localStorage.getItem('rk_theme') } catch {}
    const sys = window.matchMedia?.('(prefers-color-scheme: dark)').matches ? 'dark' : 'light'
    mode.value = (saved === 'dark' || saved === 'light') ? (saved as ThemeMode) : sys
    document.documentElement.dataset.theme = mode.value
  }

  const toggle = () => set(mode.value === 'dark' ? 'light' : 'dark')

  return { mode, set, init, toggle }
}
