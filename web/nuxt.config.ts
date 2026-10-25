// Rakeez SPA — served from /app/ by nginx; API proxied at /api.
export default defineNuxtConfig({
  ssr: false,
  devtools: { enabled: false },
  compatibilityDate: '2025-01-01',
  css: ['~/assets/css/main.css'],
  app: {
    baseURL: '/app/',
    head: {
      title: 'ركائز — منصّة الأعمال',
      htmlAttrs: { lang: 'ar', dir: 'rtl' },
      meta: [{ name: 'viewport', content: 'width=device-width, initial-scale=1' }, { name: 'theme-color', content: '#e11d48' }, { name: 'apple-mobile-web-app-capable', content: 'yes' }, { name: 'mobile-web-app-capable', content: 'yes' }, { name: 'apple-mobile-web-app-title', content: 'ركائز' }],
      link: [
        { rel: 'manifest', href: '/app/manifest.webmanifest' },
        { rel: 'icon', href: '/app/icon.svg', type: 'image/svg+xml' },
        { rel: 'apple-touch-icon', href: '/app/apple-touch-icon.png' },
        { rel: 'preconnect', href: 'https://fonts.googleapis.com' },
        { rel: 'preconnect', href: 'https://fonts.gstatic.com', crossorigin: '' },
        { rel: 'stylesheet', href: 'https://fonts.googleapis.com/css2?family=Cairo:wght@400;500;600;700;800&display=swap' },
        { rel: 'stylesheet', href: 'https://cdn.jsdelivr.net/npm/@tabler/icons-webfont@3.30.0/dist/tabler-icons.min.css' },
      ],
    },
  },
  runtimeConfig: {
    public: {
      apiBase: process.env.NUXT_PUBLIC_API_BASE || '/api',
    },
  },
})
