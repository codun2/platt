// Route guard: public pages (login/register) vs tenant app vs super-admin area,
// plus module gating: engine routes whose module is not enabled for the tenant
// (i.e. absent from the dynamic sidebar) land on /locked instead of 403s.
const ENGINE_PREFIXES = ['inventory', 'pos', 'vehicles', 'bookings', 'travel', 'rental', 'service', 'projects', 'education', 'manufacturing', 'accounting', 'crm', 'hr', 'whatsapp']

export default defineNuxtRouteMiddleware(async (to) => {
  const auth = useAuth()
  const publicPages = ['/login', '/register']

  if (!auth.state.value.loaded) await auth.fetchMe()

  const loggedIn = auth.isLoggedIn.value
  const isSuper = auth.isSuper.value

  if (!loggedIn && !publicPages.includes(to.path)) return navigateTo('/login')
  if (loggedIn && publicPages.includes(to.path)) return navigateTo(isSuper ? '/super' : '/')
  if (loggedIn && to.path.startsWith('/super') && !isSuper) return navigateTo('/')
  if (loggedIn && isSuper && !to.path.startsWith('/super')) return navigateTo('/super')

  if (loggedIn && !isSuper) {
    const seg = to.path.split('/')[1]
    if (ENGINE_PREFIXES.includes(seg)) {
      const enabled = auth.state.value.sidebar.some(i => (i.route || '').startsWith('/' + seg) || i.children.some(c => (c.route || '').startsWith('/' + seg)))
      if (!enabled) return navigateTo({ path: '/locked', query: { m: seg } })
    }
  }
})
