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
  function applyAccent(color?: string | null) {
    if (!import.meta.client) return
    const el = document.documentElement
    color && /^#[0-9a-f]{6}$/i.test(color) ? el.style.setProperty('--primary', color) : el.style.removeProperty('--primary')
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
