// Rakeez SPA — served from /app/ by nginx; API proxied at /api.
// Set the colour theme on <html> before first paint, so dark mode never flashes light.
const THEME = "(function(){try{var t=localStorage.getItem('rk_theme');if(t!=='dark'&&t!=='light'){t=(window.matchMedia&&matchMedia('(prefers-color-scheme: dark)').matches)?'dark':'light';}document.documentElement.setAttribute('data-theme',t);}catch(e){}})();"
const RECOVERY = "(function(){var K='rk_recover_ts';function hit(m){m=(''+(m||'')).toLowerCase();return m.indexOf('importing a module script failed')>-1||m.indexOf('error loading dynamically')>-1||m.indexOf('failed to fetch dynamically')>-1||m.indexOf('dynamically imported module')>-1;}function bust(){try{var t=+sessionStorage.getItem(K)||0;if(Date.now()-t<20000)return;sessionStorage.setItem(K,''+Date.now());}catch(e){}if(!navigator.onLine)return;var done=function(){location.reload();};try{var ps=[];if(window.caches&&caches.keys){ps.push(caches.keys().then(function(ks){return Promise.all(ks.map(function(k){return caches.delete(k);}));}));}if(navigator.serviceWorker&&navigator.serviceWorker.getRegistrations){ps.push(navigator.serviceWorker.getRegistrations().then(function(rs){return Promise.all(rs.map(function(r){return r.update&&r.update();}));}));}Promise.all(ps).then(done,done);}catch(e){done();}}addEventListener('error',function(e){if(hit(e&&e.message))bust();},true);addEventListener('unhandledrejection',function(e){var r=e&&e.reason;if(hit(r&&(r.message||r)))bust();});addEventListener('load',function(){setTimeout(function(){try{var n=document.getElementById('__nuxt');if(n&&n.children.length)sessionStorage.removeItem(K);}catch(e){}},5000);});})();"

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
      script: [{ innerHTML: THEME, tagPosition: 'head' }, { innerHTML: RECOVERY, tagPosition: 'head' }],
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
