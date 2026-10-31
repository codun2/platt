<script setup lang="ts">
definePageMeta({ layout: 'auth' })
const auth = useAuth()
const { t, tt } = useI18n()
const email = ref('')
const password = ref('')
const err = ref('')
const busy = ref(false)
const show = ref(false)
const quick = ref<any[]>([])
onMounted(async () => { try { quick.value = await useApi().get('/public/quick-logins') } catch { /* */ } })
async function quickLogin(acc: any) {
  err.value = ''; busy.value = true
  try { const res = await auth.demoLogin(acc.email); navigateTo(res.user.is_super_admin ? '/super' : '/') }
  catch (e: any) { err.value = e.message } finally { busy.value = false }
}

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
      <div class="fld"><i class="ti ti-mail fld-ic" /><input id="f-email" v-model="email" type="email" required placeholder=" " autocomplete="username" /><label for="f-email">{{ t('auth.email') }}</label></div>
      <div class="fld has-eye"><i class="ti ti-lock fld-ic" /><input id="f-pass" v-model="password" :type="show ? 'text' : 'password'" required placeholder=" " autocomplete="current-password" /><label for="f-pass">{{ t('auth.password') }}</label><button type="button" class="fld-eye" :title="show ? tt('إخفاء','Hide') : tt('إظهار','Show')" @click="show = !show"><i :class="`ti ${show ? 'ti-eye-off' : 'ti-eye'}`" /></button></div>
      <button class="btn big" :disabled="busy"><span v-if="busy" class="spin"><i class="ti ti-loader-2" /></span><template v-else>{{ t('auth.loginBtn') }} <i class="ti ti-arrow-left rtl-flip" /></template></button>
    </form>
    <div v-if="quick.length" class="quick-logins">
      <div class="q-sep"><span>{{ tt('أو جرّب حساباً جاهزاً', 'Or try a ready account') }}</span></div>
      <div class="q-grid">
        <button v-for="a in quick" :key="a.email" type="button" class="q-chip" :disabled="busy" @click="quickLogin(a)">
          <i :class="`ti ${a.icon}`" /> {{ tt(a.name_ar, a.name_en || a.name_ar) }}
        </button>
      </div>
    </div>
    <p class="switch">{{ t('auth.noAccount') }} <NuxtLink to="/register">{{ tt('سجّل شركتك مجاناً', 'Register free') }}</NuxtLink></p>
  </div>
</template>
