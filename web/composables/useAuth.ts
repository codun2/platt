export interface SidebarItem {
  key: string; name_ar: string; name_en: string | null; icon: string | null; route: string | null; engine: string; children: SidebarItem[]
}
export interface AuthState {
  user: any | null
  tenant: any | null
  permissions: string[]
  sidebar: SidebarItem[]
  identity?: { key: string; accent: string; org?: [string, string] } | null
  /** Enabled module keys, including advanced add-ons (loyalty, orders…). */
  modules?: string[]
  loaded: boolean
}

export function useAuth() {
  const api = useApi()
  const state = useState<AuthState>('auth', () => ({ user: null, tenant: null, permissions: [], sidebar: [], loaded: false }))

  function apply(payload: any) {
    state.value = {
      user: payload.user,
      tenant: payload.tenant,
      permissions: payload.permissions || [],
      sidebar: payload.sidebar || [],
      identity: payload.identity || null,
      modules: payload.modules || [],
      loaded: true,
    }
    applyAccent(payload.identity?.accent)
    if (import.meta.client) {
      // Last session kept on the device so the app still opens without internet.
      try { localStorage.setItem('rakeez_me', JSON.stringify(payload)) } catch { /* storage full / private mode */ }
      if (payload.tenant?.id) {
        startOffline(payload.tenant.id)
        // Save the main lists on the device so they open without internet too.
        setTimeout(() => warmUp((payload.sidebar || []).map((i: any) => i.key)), 4000)
      }
    }
  }

  // Per-sector accent: everything themed off --primary follows it.
  // Shades are computed here as plain colours (not CSS color-mix) so older
  // browsers still get a visible hover/active state.
  function applyAccent(color?: string | null) {
    if (!import.meta.client) return
    const el = document.documentElement
    const vars = ['--primary', '--primary-600', '--primary-50', '--primary-ring']
    if (!color || !/^#[0-9a-f]{6}$/i.test(color)) { vars.forEach(v => el.style.removeProperty(v)); return }
    const [r, g, b] = [1, 3, 5].map(i => parseInt(color.slice(i, i + 2), 16))
    const mix = (t: number, k: number) => [r, g, b].map(c => Math.round(c * k + t * (1 - k)))
    const hex = (a: number[]) => '#' + a.map(c => c.toString(16).padStart(2, '0')).join('')
    el.style.setProperty('--primary', color)
    el.style.setProperty('--primary-600', hex(mix(0, 0.82)))
    el.style.setProperty('--primary-50', hex(mix(255, 0.08)))
    el.style.setProperty('--primary-ring', `rgba(${r}, ${g}, ${b}, .14)`)
  }

  async function login(email: string, password: string) {
    const res = await api.post('/auth/login', { email, password })
    api.setToken(res.token)
    apply(res)
    return res
  }

  async function register(form: Record<string, any>) {
    const res = await api.post('/auth/register', form)
    api.setToken(res.token)
    apply(res)
    return res
  }

  async function fetchMe() {
    if (!api.token.value) { state.value.loaded = true; return null }
    try {
      const res = await api.get('/auth/me')
      apply(res)
      return res
    } catch (e: any) {
      // No internet: keep working with the last session saved on this device.
      if (e?.offline) {
        try { const cached = JSON.parse(localStorage.getItem('rakeez_me') || 'null'); if (cached?.user) { apply(cached); return cached } } catch { /* corrupt copy */ }
      }
      state.value = { user: null, tenant: null, permissions: [], sidebar: [], loaded: true }
      return null
    }
  }

  async function logout() {
    const off = useOffline()
    if (off.state.pending > 0 && !(await uiConfirm(tt(`يوجد ${off.state.pending} عملية محفوظة على الجهاز لم تُرفع بعد. ستُرفع عند تسجيل دخولك مجدداً من نفس الجهاز. تسجيل الخروج الآن؟`, `${off.state.pending} operations are waiting to upload. They will upload when you sign in again on this device. Sign out now?`), { tone: 'warn' }))) return
    try { await api.post('/auth/logout') } catch {}
    try { localStorage.removeItem('rakeez_me'); navigator.serviceWorker?.controller?.postMessage('CLEAR_API') } catch { /* no SW */ }
    api.setToken(null)
    state.value = { user: null, tenant: null, permissions: [], sidebar: [], loaded: true }
    applyAccent(null)
    navigateTo('/login')
  }

  const can = (perm: string) => !!state.value.user?.is_owner || !!state.value.user?.is_super_admin || state.value.permissions.includes(perm)
  const isSuper = computed(() => !!state.value.user?.is_super_admin)
  const isLoggedIn = computed(() => !!state.value.user)

  /** Is a module / add-on enabled for this tenant (e.g. hasModule('loyalty'))? */
  const hasModule = (key: string) => (state.value.modules || []).includes(key)
  return { state, login, register, fetchMe, logout, can, isSuper, isLoggedIn, hasModule }
}
