/**
 * Keeps an unsaved form when the user jumps to an "add" page from a dropdown
 * (UiSelect addTo) and restores it on return. Usage in a "new" page:
 *   const form = reactive(blankX()); useDraft('purchase-new', form)
 * Saved only on that jump (event `rakeez:leave-for-add`), restored only when
 * coming back with ?for=…, then cleared.
 */
export function useDraft(key: string, form: Record<string, any>) {
  if (!import.meta.client) return
  const route = useRoute()
  const sk = `rakeez_draft:${key}`
  const save = () => { try { sessionStorage.setItem(sk, JSON.stringify(form)) } catch { /* storage unavailable */ } }
  // Restore during setup (before child dropdowns mount and select the newly
  // created record), so the draft never overwrites that selection.
  if (route.query.for) {
    try { const d = sessionStorage.getItem(sk); if (d) Object.assign(form, JSON.parse(d)) } catch { /* ignore */ }
  }
  try { sessionStorage.removeItem(sk) } catch { /* ignore */ }
  onMounted(() => window.addEventListener('rakeez:leave-for-add', save))
  onBeforeUnmount(() => window.removeEventListener('rakeez:leave-for-add', save))
}

/** On an add page: after saving, go back to the page that sent us (if any). */
export function returnAfterCreate(id: any): boolean {
  const q = useRoute().query
  const back = typeof q.return === 'string' && q.return.startsWith('/') ? q.return : null
  if (!back) return false
  const sep = back.includes('?') ? '&' : '?'
  navigateTo(`${back}${sep}for=${encodeURIComponent(String(q.for || ''))}&created=${encodeURIComponent(String(id))}`)
  return true
}

/** Cancel link on an add page: back to the sending form (draft restored), else the fallback. */
export function cancelLink(fallback: string): string {
  const q = useRoute().query
  const back = typeof q.return === 'string' && q.return.startsWith('/') ? q.return : null
  if (!back) return fallback
  return `${back}${back.includes('?') ? '&' : '?'}for=${encodeURIComponent(String(q.for || ''))}`
}
