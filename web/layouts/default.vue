<script setup lang="ts">
import type { SidebarItem } from '~/composables/useAuth'
const auth = useAuth()
const route = useRoute()
const { t, isEn } = useI18n()
// Page title: a list page whose route is a menu item takes that item's
// (sector-aware) label, so the header matches the menu ("المحاصيل" rather than
// the generic "الأصناف"). Other pages use route.meta.title / titleEn.
const menuLabel = computed(() => {
  let hit: SidebarItem | null = null
  const walk = (items: SidebarItem[]) => { for (const i of items) { if (i.route === route.path) hit = i; walk(i.children || []) } }
  walk(auth.state.value.sidebar || [])
  const h = hit as SidebarItem | null
  return h ? ((isEn.value && h.name_en) || h.name_ar) : null
})
const title = computed(() => menuLabel.value || (isEn.value && (route.meta.titleEn as string)) || (route.meta.title as string) || t('app.name'))
const sector = computed(() => { const p = auth.state.value.tenant?.sector_preset; return p ? ((isEn.value && p.name_en) || p.name_ar) : '' })
</script>

<template>
  <div class="shell">
    <AppSidebar :items="auth.state.value.sidebar" :brand="auth.state.value.tenant?.name || 'ركائز'" :sub="sector" />
    <div class="main">
      <TopBar :title="title" />
      <main class="content"><slot /></main>
      <footer class="footer row between">
        <span>{{ t('footer.platform') }}</span>
        <span class="badge" :class="auth.state.value.tenant?.subscription_status === 'trial' ? 'warn' : 'ok'">
          {{ auth.state.value.tenant?.subscription_status === 'trial' ? t('footer.trial') : t('footer.active') }}
          · {{ t('footer.renews') }} {{ auth.state.value.tenant?.subscription_renews_at }}
        </span>
      </footer>
    </div>
  </div>
</template>
