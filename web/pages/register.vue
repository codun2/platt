<script setup lang="ts">
definePageMeta({ layout: 'auth' })
const api = useApi()
const auth = useAuth()
const { tt } = useI18n()
const sectors = ref<any[]>([])
const form = reactive({ company_name: '', sector: '', name: '', email: '', phone: '', password: '' })
const err = ref(''); const busy = ref(false); const show = ref(false); const q = ref('')

onMounted(async () => { try { sectors.value = await api.get('/public/sectors') } catch { /* offline */ } })
const shown = computed(() => { const s = q.value.trim(); return s ? sectors.value.filter((x: any) => (x.name_ar + ' ' + (x.name_en || '')).includes(s)) : sectors.value })
const picked = computed(() => sectors.value.find((s: any) => s.key === form.sector))

async function submit() {
  if (!form.sector) { err.value = tt('اختر قطاع نشاطك أولاً', 'Select your sector first'); return }
  err.value = ''; busy.value = true
  try { await auth.register(form); navigateTo('/') }
  catch (e: any) { err.value = e.message; window.scrollTo({ top: 0, behavior: 'smooth' }) } finally { busy.value = false }
}
</script>

<template>
  <div class="auth-card wide">
    <h2>{{ tt('سجّل شركتك', 'Register your company') }} <span class="badge-free">{{ tt('14 يوماً مجاناً', '14 days free') }}</span></h2>
    <p class="sub">{{ tt('اختر قطاع نشاطك وسيتم تفعيل الوحدات المناسبة تلقائياً', 'Pick your sector and the right modules switch on automatically') }}</p>
    <div v-if="err" class="alert bad">{{ err }}</div>
    <form @submit.prevent="submit">
      <div class="field">
        <label>{{ tt('قطاع النشاط', 'Business sector') }} <span v-if="picked" class="muted">— {{ tt(picked.name_ar, picked.name_en || picked.name_ar) }} ✓</span></label>
        <input v-model="q" class="sec-search" :placeholder="tt('ابحث عن قطاعك…', 'Search your sector…')" />
        <div class="sectors">
          <button v-for="s in shown" :key="s.key" type="button" class="s" :class="{ on: form.sector === s.key }" @click="form.sector = s.key">
            <i :class="`ti ${s.icon || 'ti-building'}`" /><span>{{ tt(s.name_ar, s.name_en || s.name_ar) }}</span>
          </button>
          <p v-if="!shown.length" class="muted" style="grid-column:1/-1;text-align:center;padding:12px">{{ tt('لا نتائج — جرّب كلمة أخرى', 'No match') }}</p>
        </div>
      </div>
      <div class="grid grid-2">
        <div class="field ic"><label>{{ tt('اسم الشركة', 'Company name') }}</label><i class="ti ti-building-store" /><input v-model="form.company_name" required /></div>
        <div class="field ic"><label>{{ tt('اسمك', 'Your name') }}</label><i class="ti ti-user" /><input v-model="form.name" required /></div>
        <div class="field ic"><label>{{ tt('البريد الإلكتروني', 'Email') }}</label><i class="ti ti-mail" /><input v-model="form.email" type="email" required dir="ltr" placeholder="you@company.com" /></div>
        <div class="field ic"><label>{{ tt('الهاتف', 'Phone') }}</label><i class="ti ti-phone" /><input v-model="form.phone" dir="ltr" placeholder="05xxxxxxxx" /></div>
        <div class="field ic" style="grid-column:1/-1"><label>{{ tt('كلمة المرور', 'Password') }}</label><i class="ti ti-lock" /><input v-model="form.password" :type="show ? 'text' : 'password'" minlength="6" required dir="ltr" placeholder="••••••••" /><button type="button" class="eye" @click="show = !show"><i :class="`ti ${show ? 'ti-eye-off' : 'ti-eye'}`" /></button></div>
      </div>
      <button class="btn big" :disabled="busy"><span v-if="busy" class="spin"><i class="ti ti-loader-2" /></span><template v-else>{{ tt('إنشاء الحساب وبدء التجربة', 'Create account & start') }} <i class="ti ti-arrow-left rtl-flip" /></template></button>
    </form>
    <p class="switch">{{ tt('لديك حساب؟', 'Have an account?') }} <NuxtLink to="/login">{{ tt('تسجيل الدخول', 'Sign in') }}</NuxtLink></p>
  </div>
</template>
