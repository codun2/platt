<script setup lang="ts">
defineProps<{ title: string }>()
const auth = useAuth()
const now = ref(new Date())
let t: any
onMounted(() => { t = setInterval(() => (now.value = new Date()), 1000) })
onUnmounted(() => clearInterval(t))
const time = computed(() => now.value.toLocaleTimeString('en-GB'))
const date = computed(() => now.value.toISOString().slice(0, 10))
const initials = computed(() => (auth.state.value.user?.name || '?').slice(0, 1))
</script>

<template>
  <header class="topbar">
    <h1>{{ title }}</h1>
    <div class="spacer" />
    <div class="clock">{{ time }}<br />{{ date }}</div>
    <div class="user">
      <div class="avatar">{{ initials }}</div>
      <div>
        <b style="font-size:13px">{{ auth.state.value.user?.name }}</b><br />
        <small class="muted">{{ auth.state.value.tenant?.name || 'إدارة المنصّة' }}</small>
      </div>
      <button class="btn ghost sm" title="تسجيل الخروج" @click="auth.logout()"><i class="ti ti-logout" /></button>
    </div>
  </header>
</template>
