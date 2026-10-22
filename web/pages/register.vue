<script setup lang="ts">
definePageMeta({ layout: 'auth' })
const api = useApi()
const auth = useAuth()
const { tt, toggle, isEn } = useI18n()
const sectors = ref<any[]>([])
const form = reactive({ company_name: '', sector: '', name: '', email: '', phone: '', password: '' })
const err = ref('')
const busy = ref(false)

onMounted(async () => { sectors.value = await api.get('/public/sectors') })

async function submit() {
  if (!form.sector) { err.value = tt('اختر قطاع نشاطك أولاً','Select your sector first'); return }
  err.value = ''; busy.value = true
  try { await auth.register(form); navigateTo('/') }
  catch (e: any) { err.value = e.message } finally { busy.value = false }
}
</script>

<template>
  <div class="box wide">
    <div style="text-align:left;margin-bottom:4px"><button class="btn ghost sm" @click="toggle"><i class="ti ti-language" /> {{ isEn ? 'ع' : 'EN' }}</button></div>
    <div class="logo"><div class="mark">{{ tt('ر','R') }}</div><div><b>{{ tt('ركائز','Rakeez') }}</b><br /><small class="muted">{{ tt('تجربة مجانية 14 يوماً','14-day free trial') }}</small></div></div>
    <h2>{{ tt('سجّل شركتك','Register your company') }}</h2>
    <p class="sub">{{ tt('اختر قطاع نشاطك وسيتم تفعيل الوحدات المناسبة تلقائياً','Pick your sector and the right modules will be enabled automatically') }}</p>
    <div v-if="err" class="alert bad">{{ err }}</div>
    <form @submit.prevent="submit">
      <div class="field">
        <label>{{ tt('قطاع النشاط','Business sector') }}</label>
        <div class="sectors">
          <div v-for="s in sectors" :key="s.key" class="s" :class="{ on: form.sector === s.key }" @click="form.sector = s.key">
            <i :class="`ti ${s.icon || 'ti-building'}`" />{{ tt(s.name_ar, s.name_en || s.name_ar) }}
          </div>
        </div>
      </div>
      <div class="grid grid-2">
        <div class="field"><label>{{ tt('اسم الشركة','Company name') }}</label><input v-model="form.company_name" required /></div>
        <div class="field"><label>{{ tt('اسمك','Your name') }}</label><input v-model="form.name" required /></div>
        <div class="field"><label>{{ tt('البريد الإلكتروني','Email') }}</label><input v-model="form.email" type="email" required dir="ltr" /></div>
        <div class="field"><label>{{ tt('الهاتف','Phone') }}</label><input v-model="form.phone" dir="ltr" /></div>
        <div class="field"><label>{{ tt('كلمة المرور','Password') }}</label><input v-model="form.password" type="password" minlength="6" required dir="ltr" /></div>
      </div>
      <button class="btn" style="width:100%;justify-content:center" :disabled="busy">{{ busy ? '...' : tt('إنشاء الحساب','Create account') }}</button>
    </form>
    <p class="mt muted" style="text-align:center">{{ tt('لديك حساب؟','Already have an account?') }} <NuxtLink to="/login" style="color:var(--primary);font-weight:700">{{ tt('تسجيل الدخول','Sign in') }}</NuxtLink></p>
  </div>
</template>
