// Sync the reactive theme state with the attribute the inline head script
// already set, so the toggle button starts from the right value.
export default defineNuxtPlugin(() => {
  useTheme().init()
})
