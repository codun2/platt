<script setup lang="ts">
definePageMeta({ layout: 'auth' })
const auth = useAuth()
const { t, tt, toggle, isEn } = useI18n()
const email = ref('')
const password = ref('')
const err = ref('')
const busy = ref(false)

async function submit() {
  err.value = ''; busy.value = true
  try {
    const res = await auth.login(email.value, password.value)
    navigateTo(res.user.is_super_admin ? '/super' : '/')
  } catch (e: any) { err.value = e.message } finally { busy.value = false }
}
</script>

<template>
  <div class="box">
    <div class="row between">
      <div class="logo"><div class="mark">ر</div><div><b>{{ t('app.name') }}</b><br /><small class="muted">{{ t('app.tagline') }}</small></div></div>
      <button class="btn ghost sm" @click="toggle"><i class="ti ti-language" /> {{ isEn ? 'ع' : 'EN' }}</button>
    </div>
    <h2>{{ t('auth.login') }}</h2>
    <p class="sub">{{ tt('أدخل بياناتك للوصول إلى لوحة التحكم', 'Enter your credentials to access the dashboard') }}</p>
    <div v-if="err" class="alert bad">{{ err }}</div>
    <form @submit.prevent="submit">
      <div class="field"><label>{{ t('auth.email') }}</label><input v-model="email" type="email" required dir="ltr" /></div>
      <div class="field"><label>{{ t('auth.password') }}</label><input v-model="password" type="password" required dir="ltr" /></div>
      <button class="btn" style="width:100%;justify-content:center" :disabled="busy">{{ busy ? '...' : t('auth.loginBtn') }}</button>
    </form>
    <p class="mt muted" style="text-align:center">{{ t('auth.noAccount') }} <NuxtLink to="/register" style="color:var(--primary);font-weight:700">{{ tt('سجّل شركتك مجاناً', 'Register your company free') }}</NuxtLink></p>
  </div>
</template>
