<script setup lang="ts">
import type { SidebarItem } from '~/composables/useAuth'
const route = useRoute()
const { t, isEn } = useI18n()
const title = computed(() => (isEn.value && (route.meta.titleEn as string)) || (route.meta.title as string) || t('app.platformAdmin'))

// Platform admin menu is static on purpose: it is not a tenant module.
const items: SidebarItem[] = [
  { key: 'super', name_ar: 'نظرة عامة', name_en: 'Overview', icon: 'ti-layout-dashboard', route: '/super', engine: 'platform', children: [] },
  { key: 'tenants', name_ar: 'المشتركون', name_en: 'Tenants', icon: 'ti-building-community', route: '/super/tenants', engine: 'platform', children: [] },
  { key: 'subs', name_ar: 'الاشتراكات', name_en: 'Subscriptions', icon: 'ti-credit-card', route: '/super/subscriptions', engine: 'platform', children: [] },
  { key: 'presets', name_ar: 'الباقات (القطاعات)', name_en: 'Presets', icon: 'ti-category', route: '/super/presets', engine: 'platform', children: [] },
  { key: 'modules', name_ar: 'سجل الوحدات', name_en: 'Modules', icon: 'ti-apps', route: '/super/modules', engine: 'platform', children: [] },
  { key: 'plans', name_ar: 'خطط الأسعار', name_en: 'Plans', icon: 'ti-tag', route: '/super/plans', engine: 'platform', children: [] },
]
</script>

<template>
  <div class="shell">
    <AppSidebar :items="items" brand="إدارة المنصّة" sub="Super Admin" />
    <div class="main">
      <TopBar :title="title" />
      <main class="content"><slot /></main>
      <footer class="footer">ركائز — لوحة تحكم المنصّة</footer>
    </div>
  </div>
</template>
