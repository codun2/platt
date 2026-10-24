/** Let a page override the top-bar title with a live (sector-aware) value. */
export function usePageTitle(title: () => string | null | undefined) {
  const state = useState<string | null>('page-title', () => null)
  watchEffect(() => { state.value = title() || null })
  onBeforeUnmount(() => { state.value = null })
}
