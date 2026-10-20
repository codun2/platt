// Route guard: public pages (login/register) vs tenant app vs super-admin area.
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
})
