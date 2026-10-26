<script setup lang="ts">
definePageMeta({ title: 'بيانات الشركة', titleEn: 'Company Settings' })
const api = useApi()
const auth = useAuth()
const { tt, isEn } = useI18n()
// What this business is called in its sector: المحل، المزرعة، المدرسة…
const org = computed(() => { const o = auth.state.value.identity?.org || ['الشركة', 'Company']; return isEn.value ? o[1] : o[0] })
const data = ref<any>(null)
const form = reactive<any>({ name: '', phone: '', email: '', currency: 'SAR', tax_rate: 0, settings: { address: '', tax_number: '', cr_number: '', bank_details: '', invoice_terms: '', invoice_footer: '', einvoice_enabled: false } })
const msg = ref(''); const err = ref('')

async function load() {
  data.value = await api.get('/app/company')
  const t = data.value.tenant; const s = t.settings || {}
  Object.assign(form, { name: t.name, phone: t.phone || '', email: t.email || '', currency: t.currency || 'SAR', tax_rate: +t.tax_rate || 0 })
  Object.assign(form.settings, { address: s.address || '', tax_number: s.tax_number || '', cr_number: s.cr_number || '', bank_details: s.bank_details || '', invoice_terms: s.invoice_terms || '', invoice_footer: s.invoice_footer || '', einvoice_enabled: !!s.einvoice_enabled })
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

const vatValid = computed(() => /^3\d{13}3$/.test(String(form.settings.tax_number || '')))
async function save() {
  msg.value = ''; err.value = ''
  try { await api.patch('/app/company', form); msg.value = tt('تم الحفظ','Saved'); await auth.fetchMe() }
  catch (e: any) { err.value = e.message }
}
</script>

<template>
  <div>
    <div class="breadcrumb">{{ tt('الإعدادات العامة','General settings') }} › <b>{{ tt('بيانات', '') }} {{ org }}{{ isEn ? ' profile' : '' }}</b></div>
    <div v-if="msg" class="alert ok">{{ msg }}</div>
    <div v-if="err" class="alert bad">{{ err }}</div>
    <div class="grid grid-2" style="align-items:start">
      <div>
        <div class="card">
          <div class="card-title"><i class="ti ti-building" /> {{ org }}</div>
          <div class="field"><label>{{ tt('اسم', '') }} {{ org }}{{ isEn ? ' name' : '' }}</label><input v-model="form.name" /></div>
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
          <div class="card-title" style="margin-top:14px"><i class="ti ti-qrcode" /> {{ tt('الفاتورة الإلكترونية (ZATCA)','E-invoicing (ZATCA)') }}</div>
          <p class="muted" style="margin-top:0">{{ tt('عند التفعيل يُطبع رمز QR الضريبي (TLV) على الفواتير وفواتير نقطة البيع، مع مسمّى «فاتورة ضريبية» والرقم الضريبي — متوافق مع المرحلة الأولى لهيئة الزكاة والضريبة والجمارك.','When enabled, the ZATCA tax QR (TLV) is printed on invoices and POS receipts with the “Tax Invoice” label and VAT number — ZATCA Phase 1 compliant.') }}</p>
          <label class="switch" style="margin-bottom:10px;display:flex"><input v-model="form.settings.einvoice_enabled" type="checkbox" /><span /> {{ tt('تفعيل الفاتورة الضريبية الإلكترونية','Enable e-invoicing') }}</label>
          <div v-if="form.settings.einvoice_enabled && form.settings.tax_number && !vatValid" class="alert warn" style="margin-bottom:10px"><i class="ti ti-alert-triangle" /> {{ tt('الرقم الضريبي غير مطابق للصيغة السعودية (15 رقماً يبدأ وينتهي بـ3). سيُطبع كما هو — تأكّد منه.','The VAT number is not in the Saudi format (15 digits starting and ending with 3). It will print as-is.') }}</div>
          <div v-if="form.settings.einvoice_enabled && !form.settings.tax_number" class="alert warn" style="margin-bottom:10px"><i class="ti ti-alert-triangle" /> {{ tt('أدخل الرقم الضريبي أعلاه ليظهر رمز QR على الفواتير.','Enter the VAT number above so the QR appears on invoices.') }}</div>
          <small class="muted" style="display:block;margin-bottom:10px">{{ tt('ملاحظة: المرحلة الثانية (الربط المباشر وتوقيع XML مع منصة فاتورة) تتطلب تسجيلاً خاصاً لدى الهيئة وتُضاف لاحقاً.','Note: Phase 2 (XML signing & clearance via Fatoora) needs ZATCA onboarding and is added later.') }}</small>
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
