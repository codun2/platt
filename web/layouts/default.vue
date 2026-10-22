<script setup lang="ts">
const auth = useAuth()
const route = useRoute()
const { t, isEn } = useI18n()
// Page title: pages set route.meta.title (ar) and optionally titleEn.
const title = computed(() => (isEn.value && (route.meta.titleEn as string)) || (route.meta.title as string) || t('app.name'))
</script>

<template>
  <div class="shell">
    <AppSidebar :items="auth.state.value.sidebar" :brand="auth.state.value.tenant?.name || 'ركائز'" :sub="auth.state.value.tenant?.sector_preset?.name_ar" />
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
