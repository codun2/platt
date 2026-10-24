<script setup lang="ts">
definePageMeta({ title: 'بيانات الشركة', titleEn: 'Company Settings' })
const api = useApi()
const auth = useAuth()
const { tt } = useI18n()
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

// Only this account's own modules, named as its sector menu names them —
// other sectors' modules (travel, schools…) are not shown to a tenant.
const myModules = computed(() => {
  const menu = new Map<string, any>((auth.state.value.sidebar || []).map((i: any) => [i.key, i]))
  return (data.value?.modules || [])
    .filter((m: any) => menu.has(m.key) && m.key !== 'dashboard' && m.key !== 'settings')
    .sort((a: any, b: any) => [...menu.keys()].indexOf(a.key) - [...menu.keys()].indexOf(b.key))
    .map((m: any) => { const i = menu.get(m.key); return { ...m, name_ar: i.name_ar, name_en: i.name_en, icon: i.icon } })
})

async function save() {
  msg.value = ''; err.value = ''
  try { await api.patch('/app/company', form); msg.value = tt('تم الحفظ','Saved'); await auth.fetchMe() }
  catch (e: any) { err.value = e.message }
}
</script>

<template>
  <div>
    <div class="breadcrumb">{{ tt('الإعدادات العامة','General settings') }} › <b>{{ tt('بيانات الشركة','Company settings') }}</b></div>
    <div v-if="msg" class="alert ok">{{ msg }}</div>
    <div v-if="err" class="alert bad">{{ err }}</div>
    <div class="grid grid-2" style="align-items:start">
      <div>
        <div class="card">
          <div class="card-title"><i class="ti ti-building" /> {{ tt('الشركة','Company') }}</div>
          <div class="field"><label>{{ tt('اسم الشركة','Company name') }}</label><input v-model="form.name" /></div>
          <div class="grid grid-2">
            <div class="field"><label>{{ tt('الهاتف','Phone') }}</label><input v-model="form.phone" dir="ltr" /></div>
            <div class="field"><label>{{ tt('البريد','Email') }}</label><input v-model="form.email" dir="ltr" /></div>
            <div class="field"><label>{{ tt('العملة','Currency') }}</label><input v-model="form.currency" dir="ltr" /></div>
            <div class="field"><label>{{ tt('نسبة الضريبة الافتراضية %','Default tax rate %') }}</label><input v-model.number="form.tax_rate" type="number" step="0.01" min="0" max="100" dir="ltr" /></div>
          </div>
        </div>
        <div class="card">
          <div class="card-title"><i class="ti ti-file-invoice" /> {{ tt('بيانات تُطبع على الفواتير وعروض الأسعار','Details printed on invoices & quotes') }}</div>
          <div class="field"><label>{{ tt('العنوان','Address') }}</label><input v-model="form.settings.address" :placeholder="tt('المدينة، الشارع، المبنى','City, street, building')" /></div>
          <div class="grid grid-2">
            <div class="field"><label>{{ tt('الرقم الضريبي','Tax number') }}</label><input v-model="form.settings.tax_number" dir="ltr" /></div>
            <div class="field"><label>{{ tt('رقم السجل التجاري','Commercial registration no.') }}</label><input v-model="form.settings.cr_number" dir="ltr" /></div>
          </div>
          <div class="field"><label>{{ tt('بيانات التحويل البنكي','Bank transfer details') }}</label><textarea v-model="form.settings.bank_details" rows="2" :placeholder="tt('اسم البنك، رقم الحساب، IBAN','Bank name, account no., IBAN')" /></div>
          <div class="field"><label>{{ tt('شروط السداد الافتراضية (تظهر أسفل الفاتورة)','Default payment terms (shown on invoice)') }}</label><textarea v-model="form.settings.invoice_terms" rows="2" :placeholder="tt('مثال: يُسدَّد المبلغ خلال 30 يوماً من تاريخ الفاتورة','e.g. Payable within 30 days of invoice date')" /></div>
          <div class="field"><label>{{ tt('عبارة ختامية','Closing note') }}</label><input v-model="form.settings.invoice_footer" :placeholder="tt('شكراً لتعاملكم معنا','Thank you for your business')" /></div>
          <button class="btn" @click="save"><i class="ti ti-check" /> {{ tt('حفظ','Save') }}</button>
        </div>
      </div>
      <div class="card">
        <div class="card-title"><i class="ti ti-apps" /> {{ tt('الوحدات المفعّلة','Enabled modules') }}</div>
        <p class="muted" style="margin-top:0">{{ tt('القطاع:','Sector:') }} <b>{{ tt(data?.tenant?.sector_preset?.name_ar, data?.tenant?.sector_preset?.name_en || data?.tenant?.sector_preset?.name_ar) }}</b> · {{ tt('الخطة:','Plan:') }} <b>{{ tt(data?.tenant?.plan?.name_ar, data?.tenant?.plan?.name_en || data?.tenant?.plan?.name_ar) }}</b> ({{ tt('حتى','up to') }} {{ data?.tenant?.plan?.max_users }} {{ tt('مستخدمين','users') }})</p>
        <table class="table">
          <thead><tr><th>{{ tt('الوحدة','Module') }}</th><th>{{ tt('الحالة','Status') }}</th></tr></thead>
          <tbody>
            <tr v-for="m in myModules" :key="m.key">
              <td><i :class="`ti ${m.icon}`" /> {{ tt(m.name_ar, m.name_en || m.name_ar) }}</td>
              <td><span class="badge ok">{{ m.is_core ? tt('أساسية','Core') : tt('مفعّلة','Enabled') }}</span></td>
            </tr>
          </tbody>
        </table>
        <p class="muted mt" style="font-size:12px">{{ tt('لتفعيل وحدات إضافية تواصل مع إدارة المنصّة.','To enable more modules, contact the platform admin.') }}</p>
      </div>
    </div>
  </div>
</template>
