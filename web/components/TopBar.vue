<script setup lang="ts">
defineProps<{ title: string; back?: { to: string; label: string } | null }>()
const auth = useAuth()
const { toggle, isEn, t } = useI18n()
const now = ref(new Date())
let tm: any
onMounted(() => { tm = setInterval(() => (now.value = new Date()), 1000) })
onUnmounted(() => clearInterval(tm))
const time = computed(() => now.value.toLocaleTimeString('en-GB'))
const date = computed(() => now.value.toISOString().slice(0, 10))
const initials = computed(() => (auth.state.value.user?.name || '?').slice(0, 1))
</script>

<template>
  <header class="topbar">
    <NuxtLink v-if="back" :to="back.to" class="btn ghost sm back-btn" :title="back.label">
      <i :class="`ti ${isEn ? 'ti-arrow-left' : 'ti-arrow-right'}`" /> {{ isEn ? 'Back to' : 'رجوع إلى' }} {{ back.label }}
    </NuxtLink>
    <h1>{{ title }}</h1>
    <div class="spacer" />
    <div class="clock">{{ time }}<br />{{ date }}</div>
    <button class="btn ghost sm lang-btn" :title="t('locale.switchTo')" @click="toggle">
      <i class="ti ti-language" /> {{ isEn ? 'ع' : 'EN' }}
    </button>
    <div class="user">
      <div class="avatar">{{ initials }}</div>
      <div>
        <b style="font-size:13px">{{ auth.state.value.user?.name }}</b><br />
        <small class="muted">{{ auth.state.value.tenant?.name || t('app.platformAdmin') }}</small>
      </div>
      <button class="btn ghost sm" :title="t('common.logout')" @click="auth.logout()"><i class="ti ti-logout" /></button>
    </div>
  </header>
</template>
