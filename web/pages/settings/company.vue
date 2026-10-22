<script setup lang="ts">
definePageMeta({ title: 'بيانات الشركة' })
const api = useApi()
const auth = useAuth()
const data = ref<any>(null)
const form = reactive<any>({ name: '', phone: '', email: '', currency: 'SAR', tax_rate: 0, settings: { address: '', tax_number: '', cr_number: '', bank_details: '', invoice_terms: '', invoice_footer: '' } })
const msg = ref(''); const err = ref('')

async function load() {
  data.value = await api.get('/app/company')
  const t = data.value.tenant; const s = t.settings || {}
  Object.assign(form, { name: t.name, phone: t.phone || '', email: t.email || '', currency: t.currency || 'SAR', tax_rate: +t.tax_rate || 0 })
  Object.assign(form.settings, { address: s.address || '', tax_number: s.tax_number || '', cr_number: s.cr_number || '', bank_details: s.bank_details || '', invoice_terms: s.invoice_terms || '', invoice_footer: s.invoice_footer || '' })
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
    <div v-if="msg" class="alert ok">{{ msg }}</div>
    <div v-if="err" class="alert bad">{{ err }}</div>
    <div class="grid grid-2" style="align-items:start">
      <div>
        <div class="card">
          <div class="card-title"><i class="ti ti-building" /> الشركة</div>
          <div class="field"><label>اسم الشركة</label><input v-model="form.name" /></div>
          <div class="grid grid-2">
            <div class="field"><label>الهاتف</label><input v-model="form.phone" dir="ltr" /></div>
            <div class="field"><label>البريد</label><input v-model="form.email" dir="ltr" /></div>
            <div class="field"><label>العملة</label><input v-model="form.currency" dir="ltr" /></div>
            <div class="field"><label>نسبة الضريبة الافتراضية %</label><input v-model.number="form.tax_rate" type="number" step="0.01" min="0" max="100" dir="ltr" /></div>
          </div>
        </div>
        <div class="card">
          <div class="card-title"><i class="ti ti-file-invoice" /> بيانات تُطبع على الفواتير وعروض الأسعار</div>
          <div class="field"><label>العنوان</label><input v-model="form.settings.address" placeholder="المدينة، الشارع، المبنى" /></div>
          <div class="grid grid-2">
            <div class="field"><label>الرقم الضريبي</label><input v-model="form.settings.tax_number" dir="ltr" /></div>
            <div class="field"><label>رقم السجل التجاري</label><input v-model="form.settings.cr_number" dir="ltr" /></div>
          </div>
          <div class="field"><label>بيانات التحويل البنكي</label><textarea v-model="form.settings.bank_details" rows="2" placeholder="اسم البنك، رقم الحساب، IBAN" /></div>
          <div class="field"><label>شروط السداد الافتراضية (تظهر أسفل الفاتورة)</label><textarea v-model="form.settings.invoice_terms" rows="2" placeholder="مثال: يُسدَّد المبلغ خلال 30 يوماً من تاريخ الفاتورة" /></div>
          <div class="field"><label>عبارة ختامية</label><input v-model="form.settings.invoice_footer" placeholder="شكراً لتعاملكم معنا" /></div>
          <button class="btn" @click="save"><i class="ti ti-check" /> حفظ</button>
        </div>
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
