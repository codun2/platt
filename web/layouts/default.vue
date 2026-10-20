<script setup lang="ts">
const auth = useAuth()
const route = useRoute()
const title = computed(() => (route.meta.title as string) || 'لوحة التحكم')
</script>

<template>
  <div class="shell">
    <AppSidebar :items="auth.state.value.sidebar" :brand="auth.state.value.tenant?.name || 'ركائز'" :sub="auth.state.value.tenant?.sector_preset?.name_ar" />
    <div class="main">
      <TopBar :title="title" />
      <main class="content"><slot /></main>
      <footer class="footer row between">
        <span>ركائز — منصّة الأعمال المتكاملة</span>
        <span class="badge" :class="auth.state.value.tenant?.subscription_status === 'trial' ? 'warn' : 'ok'">
          {{ auth.state.value.tenant?.subscription_status === 'trial' ? 'فترة تجريبية' : 'اشتراك فعّال' }}
          · يتجدد {{ auth.state.value.tenant?.subscription_renews_at }}
        </span>
      </footer>
    </div>
  </div>
</template>
