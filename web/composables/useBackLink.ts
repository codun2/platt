import type { SidebarItem } from '~/composables/useAuth'

/**
 * "Back to list" for pages that are not themselves a menu item (a record page,
 * new/edit form…): the menu item whose route is the longest prefix of the
 * current path, e.g. /inventory/items/12 → «أصناف الحلويات» (/inventory/items).
 */
export function useBackLink(items: () => SidebarItem[]) {
  const route = useRoute()
  const { isEn } = useI18n()
  return computed(() => {
    const p = route.path
    if (p === '/') return null
    let exact = false
    let best: SidebarItem | null = null
    const walk = (list: SidebarItem[]) => {
      for (const i of list) {
        const r = i.route
        if (r === p) exact = true
        else if (r && r !== '/' && p.startsWith(r + '/') && (!best || r.length > (best.route || '').length)) best = i
        walk(i.children || [])
      }
    }
    walk(items() || [])
    const b = best as SidebarItem | null
    if (exact || !b) return null
    return { to: b.route as string, label: (isEn.value && b.name_en) || b.name_ar }
  })
}
