<script setup lang="ts">
definePageMeta({ layout: 'auth' })
const auth = useAuth()
const { t, tt } = useI18n()
const email = ref('')
const password = ref('')
const err = ref('')
const busy = ref(false)
const show = ref(false)

async function submit() {
  err.value = ''; busy.value = true
  try {
    const res = await auth.login(email.value, password.value)
    navigateTo(res.user.is_super_admin ? '/super' : '/')
  } catch (e: any) { err.value = e.message } finally { busy.value = false }
}
</script>

<template>
  <div class="auth-card">
    <h2>{{ t('auth.login') }} <span class="wave">👋</span></h2>
    <p class="sub">{{ tt('أدخل بياناتك للوصول إلى لوحة التحكم', 'Enter your credentials to access the dashboard') }}</p>
    <div v-if="err" class="alert bad">{{ err }}</div>
    <form @submit.prevent="submit">
      <div class="field ic"><label>{{ t('auth.email') }}</label><i class="ti ti-mail" /><input v-model="email" type="email" required dir="ltr" placeholder="you@company.com" autocomplete="username" /></div>
      <div class="field ic"><label>{{ t('auth.password') }}</label><i class="ti ti-lock" /><input v-model="password" :type="show ? 'text' : 'password'" required dir="ltr" placeholder="••••••••" autocomplete="current-password" /><button type="button" class="eye" @click="show = !show"><i :class="`ti ${show ? 'ti-eye-off' : 'ti-eye'}`" /></button></div>
      <button class="btn big" :disabled="busy"><span v-if="busy" class="spin"><i class="ti ti-loader-2" /></span><template v-else>{{ t('auth.loginBtn') }} <i class="ti ti-arrow-left rtl-flip" /></template></button>
    </form>
    <p class="switch">{{ t('auth.noAccount') }} <NuxtLink to="/register">{{ tt('سجّل شركتك مجاناً', 'Register free') }}</NuxtLink></p>
  </div>
</template>
