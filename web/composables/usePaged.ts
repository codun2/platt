/**
 * Browser-side pagination for lists the API returns in full (chart of
 * accounts, suppliers, catalogs…). Returns the visible slice and a
 * paginator-shaped object for <UiPager>. Jumps back to page 1 when the list
 * changes size (new filter / search).
 */
export function usePaged<T>(source: () => T[], perPage = 25) {
  const page = ref(1)
  const all = computed(() => source() || [])
  const last = computed(() => Math.max(1, Math.ceil(all.value.length / perPage)))
  watch(() => all.value.length, () => { page.value = 1 })
  watch(last, l => { if (page.value > l) page.value = l })
  const rows = computed(() => all.value.slice((page.value - 1) * perPage, page.value * perPage))
  const pager = computed(() => ({
    current_page: page.value, last_page: last.value, total: all.value.length,
    from: all.value.length ? (page.value - 1) * perPage + 1 : 0, to: Math.min(page.value * perPage, all.value.length),
  }))
  return { page, rows, pager, go: (n: number) => { page.value = n } }
}
