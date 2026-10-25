// A record saved offline has no page on the server yet (id tmp_…): open its list instead.
export default defineNuxtRouteMiddleware((to) => {
  if (!/\/tmp_[a-z0-9]+/.test(to.path)) return
  const list = to.path.replace(/\/tmp_[a-z0-9]+.*$/, '') || '/'
  if (import.meta.client) setTimeout(() => uiAlert(tt('حُفظ على هذا الجهاز وسيُرفع تلقائياً عند عودة الإنترنت. يمكنك فتح تفاصيله بعد الرفع.', 'Saved on this device; it will upload automatically when the internet is back. Details open after upload.'), { tone: 'info' } as any), 50)
  return navigateTo(list, { replace: true })
})
