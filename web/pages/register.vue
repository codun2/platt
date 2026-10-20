<script setup lang="ts">
definePageMeta({ layout: 'auth' })
const api = useApi()
const auth = useAuth()
const sectors = ref<any[]>([])
const form = reactive({ company_name: '', sector: '', name: '', email: '', phone: '', password: '' })
const err = ref('')
const busy = ref(false)

onMounted(async () => { sectors.value = await api.get('/public/sectors') })

async function submit() {
  if (!form.sector) { err.value = 'اختر قطاع نشاطك أولاً'; return }
  err.value = ''; busy.value = true
  try { await auth.register(form); navigateTo('/') }
  catch (e: any) { err.value = e.message } finally { busy.value = false }
}
</script>

<template>
  <div class="box wide">
    <div class="logo"><div class="mark">ر</div><div><b>ركائز</b><br /><small class="muted">تجربة مجانية 14 يوماً</small></div></div>
    <h2>سجّل شركتك</h2>
    <p class="sub">اختر قطاع نشاطك وسيتم تفعيل الوحدات المناسبة تلقائياً</p>
    <div v-if="err" class="alert bad">{{ err }}</div>
    <form @submit.prevent="submit">
      <div class="field">
        <label>قطاع النشاط</label>
        <div class="sectors">
          <div v-for="s in sectors" :key="s.key" class="s" :class="{ on: form.sector === s.key }" @click="form.sector = s.key">
            <i :class="`ti ${s.icon || 'ti-building'}`" />{{ s.name_ar }}
          </div>
        </div>
      </div>
      <div class="grid grid-2">
        <div class="field"><label>اسم الشركة</label><input v-model="form.company_name" required /></div>
        <div class="field"><label>اسمك</label><input v-model="form.name" required /></div>
        <div class="field"><label>البريد الإلكتروني</label><input v-model="form.email" type="email" required dir="ltr" /></div>
        <div class="field"><label>الهاتف</label><input v-model="form.phone" dir="ltr" /></div>
        <div class="field"><label>كلمة المرور</label><input v-model="form.password" type="password" minlength="6" required dir="ltr" /></div>
      </div>
      <button class="btn" style="width:100%;justify-content:center" :disabled="busy">{{ busy ? '...' : 'إنشاء الحساب' }}</button>
    </form>
    <p class="mt muted" style="text-align:center">لديك حساب؟ <NuxtLink to="/login" style="color:var(--primary);font-weight:700">تسجيل الدخول</NuxtLink></p>
  </div>
</template>
