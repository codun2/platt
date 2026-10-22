// Initialise locale + <html dir|lang> before the app paints.
export default defineNuxtPlugin(() => {
  useI18n()   // initLocale() runs on first call, reads localStorage, sets dir
})
