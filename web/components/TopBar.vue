<script setup lang="ts">
defineProps<{ title: string; back?: { to: string; label: string } | null }>()
const auth = useAuth()
const { toggle, isEn, t } = useI18n()
const now = ref(new Date())
let tm: any
onMounted(() => { tm = setInterval(() => (now.value = new Date()), 1000) })
onUnmounted(() => clearInterval(tm))
const time = computed(() => now.value.toLocaleTimeString('en-GB'))
const date = computed(() => ymd(now.value))
const off = useOffline()
const pwa = usePwaInstall()
const initials = computed(() => (auth.state.value.user?.name || '?').slice(0, 1))
</script>

<template>
  <header class="topbar">
    <NuxtLink v-if="back" :to="back.to" class="btn ghost sm back-btn" :title="back.label">
      <i :class="`ti ${isEn ? 'ti-arrow-left' : 'ti-arrow-right'}`" /> {{ isEn ? 'Back to' : 'رجوع إلى' }} {{ back.label }}
    </NuxtLink>
    <h1>{{ title }}</h1>
    <div class="spacer" />
    <NuxtLink v-if="!off.state.online || off.state.pending || off.state.failed" to="/offline" class="net-chip" :class="{ off: !off.state.online, bad: off.state.failed }">
      <i :class="`ti ${!off.state.online ? 'ti-wifi-off' : off.state.syncing ? 'ti-loader-2 spin' : off.state.failed ? 'ti-alert-triangle' : 'ti-cloud-upload'}`" />
      <span v-if="!off.state.online">{{ isEn ? 'Offline' : 'غير متصل' }}</span>
      <span v-if="off.state.pending">{{ off.state.pending }} {{ isEn ? 'to upload' : 'بانتظار الرفع' }}</span>
      <span v-if="off.state.failed">{{ off.state.failed }} {{ isEn ? 'failed' : 'فشل رفعها' }}</span>
    </NuxtLink>
    <!-- Always reachable: local data, offline readiness, update the device copy. -->
    <NuxtLink v-else to="/offline" class="btn ghost sm" :title="isEn ? 'Local data & offline readiness' : 'البيانات المحلية وجاهزية العمل دون إنترنت'">
      <i class="ti ti-cloud-check" style="color:var(--ok)" /> {{ isEn ? 'Local data' : 'البيانات المحلية' }}
    </NuxtLink>
    <button v-if="pwa.canInstall.value" class="btn ghost sm" :title="isEn ? 'Install the app on this device' : 'تثبيت التطبيق على هذا الجهاز'" @click="pwa.install()">
      <i class="ti ti-download" /> {{ isEn ? 'Install app' : 'تثبيت التطبيق' }}
    </button>
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
