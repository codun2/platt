export interface SidebarItem {
  key: string; name_ar: string; name_en: string | null; icon: string | null; route: string | null; engine: string; children: SidebarItem[]
}
export interface AuthState {
  user: any | null
  tenant: any | null
  permissions: string[]
  sidebar: SidebarItem[]
  identity?: { key: string; accent: string } | null
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
      loaded: true,
    }
    applyAccent(payload.identity?.accent)
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
    } catch {
      state.value = { user: null, tenant: null, permissions: [], sidebar: [], loaded: true }
      return null
    }
  }

  async function logout() {
    try { await api.post('/auth/logout') } catch {}
    api.setToken(null)
    state.value = { user: null, tenant: null, permissions: [], sidebar: [], loaded: true }
    applyAccent(null)
    navigateTo('/login')
  }

  const can = (perm: string) => !!state.value.user?.is_owner || !!state.value.user?.is_super_admin || state.value.permissions.includes(perm)
  const isSuper = computed(() => !!state.value.user?.is_super_admin)
  const isLoggedIn = computed(() => !!state.value.user)

  return { state, login, register, fetchMe, logout, can, isSuper, isLoggedIn }
}
