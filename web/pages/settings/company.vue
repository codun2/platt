<script setup lang="ts">
definePageMeta({ title: 'بيانات الشركة' })
const api = useApi()
const auth = useAuth()
const data = ref<any>(null)
const form = reactive({ name: '', phone: '', email: '', currency: 'OMR' })
const msg = ref(''); const err = ref('')

async function load() {
  data.value = await api.get('/app/company')
  Object.assign(form, { name: data.value.tenant.name, phone: data.value.tenant.phone || '', email: data.value.tenant.email || '', currency: data.value.tenant.currency || 'OMR' })
}
onMounted(load)

async function save() {
  msg.value = ''; err.value = ''
  try { await api.patch('/app/company', form); msg.value = 'تم الحفظ'; await auth.fetchMe() }
  catch (e: any) { err.value = e.message }
}
</script>

<template>
  <div>
    <div class="breadcrumb">الإعدادات العامة › <b>بيانات الشركة</b></div>
    <div class="grid grid-2">
      <div class="card">
        <div class="card-title"><i class="ti ti-building" /> الشركة</div>
        <div v-if="msg" class="alert ok">{{ msg }}</div>
        <div v-if="err" class="alert bad">{{ err }}</div>
        <div class="field"><label>اسم الشركة</label><input v-model="form.name" /></div>
        <div class="field"><label>الهاتف</label><input v-model="form.phone" dir="ltr" /></div>
        <div class="field"><label>البريد</label><input v-model="form.email" dir="ltr" /></div>
        <div class="field"><label>العملة</label><input v-model="form.currency" dir="ltr" /></div>
        <button class="btn" @click="save">حفظ</button>
      </div>
      <div class="card">
        <div class="card-title"><i class="ti ti-apps" /> الوحدات المفعّلة</div>
        <p class="muted" style="margin-top:0">القطاع: <b>{{ data?.tenant?.sector_preset?.name_ar }}</b> · الخطة: <b>{{ data?.tenant?.plan?.name_ar }}</b> (حتى {{ data?.tenant?.plan?.max_users }} مستخدمين)</p>
        <table class="table">
          <thead><tr><th>الوحدة</th><th>المحرّك</th><th>الحالة</th></tr></thead>
          <tbody>
            <tr v-for="m in data?.modules" :key="m.key">
              <td><i :class="`ti ${m.icon}`" /> {{ m.name_ar }}</td>
              <td class="muted">{{ m.engine }}</td>
              <td><span class="badge" :class="m.enabled ? 'ok' : 'muted'">{{ m.is_core ? 'أساسية' : (m.enabled ? 'مفعّلة' : 'غير مفعّلة') }}</span></td>
            </tr>
          </tbody>
        </table>
        <p class="muted mt" style="font-size:12px">لتفعيل وحدات إضافية تواصل مع إدارة المنصّة.</p>
      </div>
    </div>
  </div>
</template>
