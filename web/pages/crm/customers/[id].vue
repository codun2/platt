<script setup lang="ts">
definePageMeta({ title: 'ملف العميل', titleEn: 'Customer Profile' })
const api = useApi(); const auth = useAuth(); const f = useFmt(); const { tt } = useI18n()
const route = useRoute(); const id = route.params.id

const detail = ref<any>(null)
const tab = ref<'profile' | 'invoices'>('profile')
const editing = ref(route.query.edit === '1')
const err = ref(''); const msg = ref(''); const saving = ref(false)
const form = reactive<any>(blankCustomer())
const canManage = computed(() => auth.can('crm.manage'))

async function load() {
  detail.value = await api.get(`/app/customers/${id}`)
  Object.assign(form, customerToForm(detail.value.customer))
}
onMounted(load)

async function save() {
  err.value = ''; msg.value = ''; saving.value = true
  try {
    await api.patch(`/app/customers/${id}`, form)
    msg.value = tt('تم حفظ التعديلات', 'Changes saved'); editing.value = false; await load()
  } catch (e: any) { err.value = e.message } finally { saving.value = false }
}
async function remove() {
  if (!confirm(tt(`حذف العميل ${detail.value.customer.name}؟`, `Delete customer ${detail.value.customer.name}?`))) return
  try { await api.del(`/app/customers/${id}`); navigateTo('/crm/customers') } catch (e: any) { alert(e.message) }
}
const typeLabel = (t: string) => (t === 'company' ? tt('شركة / جهة', 'Company') : tt('فرد', 'Individual'))
</script>

<template>
  <div v-if="detail">
    <div class="breadcrumb"><NuxtLink to="/crm/customers">{{ tt('العملاء', 'Customers') }}</NuxtLink> › <b>{{ detail.customer.name }}</b></div>
    <div v-if="msg" class="alert ok">{{ msg }}</div>
    <div v-if="err" class="alert bad">{{ err }}</div>

    <div class="grid grid-4" style="margin-bottom:16px">
      <div class="card stat"><div class="ic" style="background:var(--info)"><i class="ti ti-user" /></div><div><b style="font-size:15px">{{ typeLabel(detail.customer.customer_type) }}</b><small>{{ detail.customer.category || tt('عميل', 'Customer') }}</small></div></div>
      <div class="card stat"><div class="ic" style="background:var(--ok)"><i class="ti ti-file-invoice" /></div><div><b>{{ (detail.invoices?.length || 0) + detail.sales.length }}</b><small>{{ tt('فاتورة', 'invoices') }} · {{ detail.quotes?.length || 0 }} {{ tt('عرض سعر', 'quotes') }}</small></div></div>
      <div class="card stat"><div class="ic" style="background:var(--warn)"><i class="ti ti-wallet" /></div><div><b style="font-size:15px">{{ f.money(detail.customer.credit_limit, true) }}</b><small>{{ tt('حد الائتمان', 'Credit limit') }}</small></div></div>
      <div class="card stat"><div class="ic" style="background:var(--primary)"><i class="ti ti-receipt" /></div><div><b style="font-size:15px">{{ f.money(detail.balance_due, true) }}</b><small>{{ tt('الرصيد المستحق', 'Balance due') }}</small></div></div>
    </div>

    <div class="card">
      <div class="row between" style="margin-bottom:4px">
        <div class="tabs" style="margin-bottom:0;border:none">
          <button :class="{ on: tab === 'profile' }" @click="tab = 'profile'">{{ tt('الملف', 'Profile') }}</button>
          <button :class="{ on: tab === 'invoices' }" @click="tab = 'invoices'">{{ tt('الفواتير والعروض', 'Invoices & quotes') }} ({{ (detail.invoices?.length || 0) + detail.sales.length + (detail.quotes?.length || 0) }})</button>
        </div>
        <div v-if="canManage && tab === 'profile'" class="row" style="gap:8px">
          <button v-if="!editing" class="btn ghost sm" @click="editing = true"><i class="ti ti-pencil" /> {{ tt('تعديل', 'Edit') }}</button>
          <button class="btn ghost sm" style="color:var(--primary)" @click="remove"><i class="ti ti-trash" /> {{ tt('حذف', 'Delete') }}</button>
        </div>
      </div>
      <hr style="border:none;border-top:1px solid var(--line);margin:0 0 16px">

      <!-- Profile: edit mode -->
      <div v-if="tab === 'profile' && editing">
        <CustomerForm :form="form" />
        <div class="row" style="gap:10px;margin-top:18px;border-top:1px solid var(--line);padding-top:16px">
          <button class="btn" :disabled="saving" @click="save"><i class="ti ti-check" /> {{ tt('حفظ التعديلات', 'Save changes') }}</button>
          <button class="btn ghost" :disabled="saving" @click="editing = false; load()">{{ tt('إلغاء', 'Cancel') }}</button>
        </div>
      </div>

      <!-- Profile: read-only -->
      <div v-else-if="tab === 'profile'" class="grid grid-3">
        <div><small class="muted">{{ tt('الاسم', 'Name') }}</small><br /><b>{{ detail.customer.name }}</b></div>
        <div><small class="muted">{{ tt('كود العميل', 'Code') }}</small><br /><b dir="ltr">{{ detail.customer.code || '—' }}</b></div>
        <div><small class="muted">{{ tt('الهاتف', 'Phone') }}</small><br /><b dir="ltr">{{ detail.customer.phone || '—' }}</b></div>
        <div><small class="muted">{{ tt('هاتف إضافي', 'Alt. phone') }}</small><br /><b dir="ltr">{{ detail.customer.phone2 || '—' }}</b></div>
        <div><small class="muted">{{ tt('واتساب', 'WhatsApp') }}</small><br /><b dir="ltr">{{ detail.customer.whatsapp || '—' }}</b></div>
        <div><small class="muted">{{ tt('البريد', 'Email') }}</small><br /><b dir="ltr">{{ detail.customer.email || '—' }}</b></div>
        <div><small class="muted">{{ tt('رقم الهوية / المستند', 'ID / document') }}</small><br /><b dir="ltr">{{ detail.customer.id_number || '—' }}</b></div>
        <div><small class="muted">{{ tt('الرقم الضريبي', 'Tax number') }}</small><br /><b dir="ltr">{{ detail.customer.tax_number || '—' }}</b></div>
        <div><small class="muted">{{ tt('الجنسية', 'Nationality') }}</small><br /><b>{{ detail.customer.nationality || '—' }}</b></div>
        <div v-if="detail.customer.customer_type === 'company'"><small class="muted">{{ tt('الشركة / الجهة', 'Company') }}</small><br /><b>{{ detail.customer.company_name || '—' }}</b></div>
        <div v-if="detail.customer.customer_type === 'company'"><small class="muted">{{ tt('الشخص المسؤول', 'Contact person') }}</small><br /><b>{{ detail.customer.contact_person || '—' }}</b></div>
        <div><small class="muted">{{ tt('المدينة / المنطقة', 'City / area') }}</small><br /><b>{{ [detail.customer.city, detail.customer.area].filter(Boolean).join(' - ') || '—' }}</b></div>
        <div><small class="muted">{{ tt('العنوان', 'Address') }}</small><br /><b>{{ detail.customer.address || '—' }}</b></div>
        <div><small class="muted">{{ tt('أيام السداد', 'Payment terms') }}</small><br /><b>{{ detail.customer.payment_terms_days != null ? detail.customer.payment_terms_days + tt(' يوم', ' days') : '—' }}</b></div>
        <div><small class="muted">{{ tt('الرصيد الافتتاحي', 'Opening balance') }}</small><br /><b dir="ltr">{{ f.money(detail.customer.opening_balance) }}</b></div>
        <div v-if="detail.customer.tags"><small class="muted">{{ tt('الوسوم', 'Tags') }}</small><br /><b>{{ detail.customer.tags }}</b></div>
        <div style="grid-column:1/-1" v-if="detail.customer.notes"><small class="muted">{{ tt('ملاحظات', 'Notes') }}</small><br /><b>{{ detail.customer.notes }}</b></div>
      </div>

      <!-- Invoices + quotes + POS sales -->
      <div v-else-if="tab === 'invoices'">
        <div class="row between" style="margin-bottom:10px">
          <div class="card-title" style="margin:0"><i class="ti ti-file-invoice" /> {{ tt('الفواتير', 'Invoices') }} ({{ detail.invoices?.length || 0 }})</div>
          <div v-if="canManage" class="row" style="gap:8px">
            <NuxtLink class="btn sm" :to="`/crm/invoices/new?customer_id=${detail.customer.id}`"><i class="ti ti-plus" /> {{ tt('فاتورة', 'Invoice') }}</NuxtLink>
            <NuxtLink class="btn ghost sm" :to="`/crm/quotes/new?customer_id=${detail.customer.id}`"><i class="ti ti-plus" /> {{ tt('عرض سعر', 'Quote') }}</NuxtLink>
          </div>
        </div>
        <table class="table">
          <thead><tr><th>{{ tt('الرقم','No.') }}</th><th>{{ tt('التاريخ','Date') }}</th><th>{{ tt('الاستحقاق','Due') }}</th><th>{{ tt('الموضوع','Subject') }}</th><th>{{ tt('الإجمالي','Total') }}</th><th>{{ tt('المدفوع','Paid') }}</th><th>{{ tt('المتبقي','Balance') }}</th><th>{{ tt('الحالة','Status') }}</th></tr></thead>
          <tbody>
            <tr v-for="inv in detail.invoices" :key="inv.id">
              <td><NuxtLink :to="`/crm/invoices/${inv.id}`" style="color:var(--info);font-weight:700" dir="ltr">{{ inv.number }}</NuxtLink></td><td>{{ f.d(inv.date) }}</td><td>{{ f.d(inv.due_date) }}</td><td class="muted">{{ inv.subject || '—' }}</td>
              <td class="num">{{ f.money(inv.total) }}</td><td class="num" style="color:var(--ok)">{{ f.money(inv.paid) }}</td><td class="num" :style="+inv.balance > 0 && inv.status !== 'cancelled' ? 'color:var(--primary);font-weight:700' : ''">{{ f.money(inv.balance) }}</td>
              <td><span class="badge" :class="invoiceStatus[inv.status]?.cls">{{ invoiceStatus[inv.status]?.label }}</span> <span v-if="inv.is_overdue" class="badge bad">{{ tt('متأخرة', 'Overdue') }}</span></td>
            </tr>
          </tbody>
        </table>
        <p v-if="!detail.invoices?.length" class="empty" style="padding:16px 0">{{ tt('لا توجد فواتير.', 'No invoices.') }}</p>

        <div class="card-title mt"><i class="ti ti-file-dollar" /> {{ tt('عروض الأسعار', 'Quotes') }} ({{ detail.quotes?.length || 0 }})</div>
        <table class="table">
          <thead><tr><th>{{ tt('الرقم','No.') }}</th><th>{{ tt('التاريخ','Date') }}</th><th>{{ tt('صالح حتى','Valid until') }}</th><th>{{ tt('الموضوع','Subject') }}</th><th>{{ tt('الإجمالي','Total') }}</th><th>{{ tt('الحالة','Status') }}</th></tr></thead>
          <tbody>
            <tr v-for="q in detail.quotes" :key="q.id">
              <td><NuxtLink :to="`/crm/quotes/${q.id}`" style="color:var(--info);font-weight:700" dir="ltr">{{ q.number }}</NuxtLink></td><td>{{ f.d(q.date) }}</td><td>{{ f.d(q.valid_until) }}</td><td class="muted">{{ q.subject || '—' }}</td>
              <td class="num">{{ f.money(q.total) }}</td><td><span class="badge" :class="quoteStatus[q.status]?.cls">{{ quoteStatus[q.status]?.label }}</span></td>
            </tr>
          </tbody>
        </table>
        <p v-if="!detail.quotes?.length" class="empty" style="padding:16px 0">{{ tt('لا توجد عروض أسعار.', 'No quotes.') }}</p>

        <template v-if="detail.sales.length">
          <div class="card-title mt"><i class="ti ti-cash-register" /> {{ tt('فواتير نقطة البيع', 'POS invoices') }} ({{ detail.sales.length }})</div>
          <table class="table">
            <thead><tr><th>{{ tt('الفاتورة','Invoice') }}</th><th>{{ tt('التاريخ','Date') }}</th><th>{{ tt('الإجمالي','Total') }}</th><th>{{ tt('المدفوع','Paid') }}</th><th>{{ tt('الدفع','Payment') }}</th><th>{{ tt('الحالة','Status') }}</th></tr></thead>
            <tbody>
              <tr v-for="s in detail.sales" :key="s.id">
                <td><NuxtLink :to="`/pos/sales?open=${s.id}`" style="color:var(--info)" dir="ltr">{{ s.number }}</NuxtLink></td><td>{{ f.d(s.date) }}</td>
                <td class="num">{{ f.money(s.total) }}</td><td class="num">{{ f.money(s.paid) }}</td><td>{{ f.methodLabel(s.payment_method) }}</td>
                <td><span class="badge" :class="s.status === 'completed' ? 'ok' : 'bad'">{{ s.status === 'completed' ? tt('مكتملة','Completed') : tt('ملغاة','Cancelled') }}</span></td>
              </tr>
            </tbody>
          </table>
        </template>
      </div>
    </div>
  </div>
</template>
