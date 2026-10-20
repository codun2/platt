<script setup lang="ts">
definePageMeta({ layout: 'auth' })
const auth = useAuth()
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
    <div class="logo"><div class="mark">ر</div><div><b>ركائز</b><br /><small class="muted">منصّة الأعمال المتكاملة</small></div></div>
    <h2>تسجيل الدخول</h2>
    <p class="sub">أدخل بياناتك للوصول إلى لوحة التحكم</p>
    <div v-if="err" class="alert bad">{{ err }}</div>
    <form @submit.prevent="submit">
      <div class="field"><label>البريد الإلكتروني</label><input v-model="email" type="email" required dir="ltr" /></div>
      <div class="field"><label>كلمة المرور</label><input v-model="password" type="password" required dir="ltr" /></div>
      <button class="btn" style="width:100%;justify-content:center" :disabled="busy">{{ busy ? '...' : 'دخول' }}</button>
    </form>
    <p class="mt muted" style="text-align:center">ليس لديك حساب؟ <NuxtLink to="/register" style="color:var(--primary);font-weight:700">سجّل شركتك مجاناً</NuxtLink></p>
    <p class="muted" style="text-align:center;font-size:12px"><a href="/">← العودة للصفحة الرئيسية</a></p>
  </div>
</template>
