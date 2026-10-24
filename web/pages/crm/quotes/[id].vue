<script setup lang="ts">
definePageMeta({ title: 'عرض السعر', titleEn: 'Quote' })
const api = useApi(); const auth = useAuth(); const f = useFmt()
const route = useRoute(); const id = Number(route.params.id); const { tt } = useI18n()

const data = ref<any>(null); const customers = ref<any[]>([])
const editing = ref(route.query.edit === '1'); const converting = ref(false)
const err = ref(''); const msg = ref(''); const saving = ref(false)
const form = reactive<any>(blankDoc('quote'))
const conv = reactive({ date: f.today(), payment_terms_days: '', status: 'sent' })
const q = computed(() => data.value?.quote)
const canManage = computed(() => auth.can('crm.manage'))
const validDays = computed(() => daysUntil(q.value?.valid_until))
const editable = computed(() => q.value && ['draft', 'sent'].includes(q.value.status))
const print = () => window.print()
const exporting = ref(false)
async function exportPdf() {
  exporting.value = true
  try { await exportDocPdf(document.querySelector('.doc-print') as HTMLElement, docPdfName(q.value.number, data.value.company?.name)) }
  catch (e: any) { err.value = e.message } finally { exporting.value = false }
}

const notFound = ref(false)
async function load() {
  try { data.value = await api.get(`/app/quotes/${id}`) } catch (e: any) { if (e.status === 404) { notFound.value = true; return } throw e }
  Object.assign(form, docToForm('quote', data.value.quote))
}
onMounted(async () => { await load(); customers.value = await api.get('/app/customers', { all: 1 }); if (route.query.print) { await nextTick(); setTimeout(() => window.print(), 400) } })

async function act(fn: () => Promise<any>, ok = tt('تم','Done')) {
  err.value = ''; msg.value = ''; saving.value = true
  try { await fn(); msg.value = ok; await load() } catch (e: any) { err.value = e.message } finally { saving.value = false; window.scrollTo({ top: 0, behavior: 'smooth' }) }
}
const save = () => act(async () => { await api.patch(`/app/quotes/${id}`, docPayload(form)); editing.value = false }, tt('تم حفظ التعديلات','Changes saved'))
const setStatus = (status: string, label: string) => act(() => api.post(`/app/quotes/${id}/status`, { status }), label)
async function convert() {
  err.value = ''; saving.value = true
  try { const r = await api.post(`/app/quotes/${id}/convert`, { ...conv, payment_terms_days: conv.payment_terms_days === '' ? null : +conv.payment_terms_days }); navigateTo(`/crm/invoices/${r.invoice.id}`) }
  catch (e: any) { err.value = e.message; saving.value = false; window.scrollTo({ top: 0, behavior: 'smooth' }) }
}
async function remove() { if (!await uiConfirm(tt('حذف عرض السعر نهائياً؟','Delete quote permanently?'))) return; try { await api.del(`/app/quotes/${id}`); navigateTo('/crm/quotes') } catch (e: any) { err.value = e.message } }
</script>

<template>
  <div v-if="q">
    <div class="no-print">
      <div class="breadcrumb"><NuxtLink to="/crm/quotes">{{ tt('عروض الأسعار','Quotes') }}</NuxtLink> › <b dir="ltr">{{ q.number }}</b></div>
      <div v-if="msg" class="alert ok">{{ msg }}</div>
      <div v-if="err" class="alert bad">{{ err }}</div>

      <div v-if="editing" class="card">
        <DocForm kind="quote" :form="form" :customers="customers" />
        <div class="row" style="gap:10px;margin-top:18px;border-top:1px solid var(--line);padding-top:16px">
          <button class="btn" :disabled="saving" @click="save"><i class="ti ti-check" /> {{ tt('حفظ التعديلات','Save changes') }}</button>
          <button class="btn ghost" :disabled="saving" @click="editing = false; load()">{{ tt('إلغاء','Cancel') }}</button>
        </div>
      </div>

      <template v-else>
        <div class="grid grid-4" style="margin-bottom:16px">
          <div class="card stat"><div class="ic" style="background:var(--info)"><i class="ti ti-file-dollar" /></div><div><b style="font-size:17px">{{ f.money(q.total, true) }}</b><small>{{ tt('قيمة العرض','Quote value') }}</small></div></div>
          <div class="card stat"><div class="ic" style="background:var(--ok)"><i class="ti ti-list-details" /></div><div><b>{{ q.items.length }}</b><small>{{ tt('بند','lines') }}</small></div></div>
          <div class="card stat"><div class="ic" :style="`background:${q.is_expired ? 'var(--primary)' : 'var(--warn)'}`"><i class="ti ti-calendar-due" /></div><div><b style="font-size:15px">{{ f.d(q.valid_until) }}</b><small v-if="validDays !== null && ['draft','sent'].includes(q.status)">{{ validDays < 0 ? tt(`انتهت منذ ${-validDays} يوم`,`expired ${-validDays}d ago`) : validDays === 0 ? tt('ينتهي اليوم','expires today') : tt(`متبقٍ ${validDays} يوم`,`${validDays}d left`) }}</small><small v-else>{{ tt('صالح حتى','Valid until') }}</small></div></div>
          <div class="card stat"><div class="ic" style="background:var(--primary)"><i class="ti ti-file-invoice" /></div><div><b v-if="q.invoice" style="font-size:15px"><NuxtLink :to="`/crm/invoices/${q.invoice.id}`" style="color:#fff0;color:var(--ink)" dir="ltr">{{ q.invoice.number }}</NuxtLink></b><b v-else style="font-size:15px">—</b><small>{{ tt('الفاتورة الناتجة','Resulting invoice') }}</small></div></div>
        </div>

        <div class="card">
          <div class="row between" style="align-items:flex-start">
            <div>
              <div class="row" style="gap:10px;align-items:center">
                <h2 style="margin:0" dir="ltr">{{ q.number }}</h2>
                <span class="badge" :class="quoteStatus[q.status]?.cls">{{ quoteStatus[q.status]?.label }}</span>
                <span v-if="q.is_expired" class="badge warn">{{ tt('تجاوز الصلاحية','Expired') }}</span>
              </div>
              <p class="muted" style="margin:6px 0 0">{{ q.subject || tt('عرض سعر','Quote') }} · {{ f.d(q.date) }}<span v-if="q.creator"> · {{ tt('أعدّه','by') }} {{ q.creator.name }}</span></p>
            </div>
            <div class="row" style="gap:8px;flex-wrap:wrap;justify-content:flex-end">
              <button class="btn ghost sm" @click="print"><i class="ti ti-printer" /> {{ tt('طباعة','Print') }}</button>
              <button class="btn ghost sm" :disabled="exporting" @click="exportPdf"><i class="ti ti-file-type-pdf" /> {{ exporting ? tt('جاري التصدير…','Exporting…') : tt('تصدير PDF','Export PDF') }}</button>
              <template v-if="canManage">
                <button v-if="q.status === 'draft'" class="btn info sm" @click="setStatus('sent', tt('تم تعليم العرض كمُرسَل','Marked as sent'))"><i class="ti ti-send" /> {{ tt('إرسال','Send') }}</button>
                <button v-if="['draft','sent'].includes(q.status)" class="btn ok sm" @click="setStatus('accepted', tt('تم قبول العرض','Quote accepted'))"><i class="ti ti-check" /> {{ tt('قبول','Accept') }}</button>
                <button v-if="['draft','sent'].includes(q.status)" class="btn ghost sm" @click="setStatus('rejected', tt('تم رفض العرض','Quote rejected'))"><i class="ti ti-x" /> {{ tt('رفض','Reject') }}</button>
                <button v-if="['draft','sent'].includes(q.status) && q.is_expired" class="btn ghost sm" @click="setStatus('expired', tt('تم تعليم العرض كمنتهٍ','Marked expired'))"><i class="ti ti-clock-off" /> {{ tt('منتهٍ','Expired') }}</button>
                <button v-if="['accepted','rejected','expired'].includes(q.status)" class="btn ghost sm" @click="setStatus('draft', tt('تم إعادة فتح العرض','Quote reopened'))"><i class="ti ti-refresh" /> {{ tt('إعادة فتح','Reopen') }}</button>
                <button v-if="q.status !== 'converted' && q.status !== 'rejected'" class="btn sm" @click="converting = !converting"><i class="ti ti-arrows-exchange" /> {{ tt('تحويل إلى فاتورة','Convert to invoice') }}</button>
                <button v-if="editable" class="btn ghost sm" @click="editing = true"><i class="ti ti-pencil" /> {{ tt('تعديل','Edit') }}</button>
                <button v-if="q.status !== 'converted'" class="btn ghost sm" style="color:var(--primary)" @click="remove"><i class="ti ti-trash" /> {{ tt('حذف','Delete') }}</button>
              </template>
            </div>
          </div>

          <div v-if="converting" class="pay-panel mt">
            <div class="card-title" style="margin-bottom:8px"><i class="ti ti-arrows-exchange" /> {{ tt('تحويل العرض إلى فاتورة','Convert quote to invoice') }}</div>
            <div class="row" style="align-items:flex-end;gap:10px">
              <div class="field" style="margin:0"><label>{{ tt('تاريخ الفاتورة','Invoice date') }}</label><input v-model="conv.date" type="date" dir="ltr" /></div>
              <div class="field" style="margin:0"><label>{{ tt('شروط السداد (أيام)','Payment terms (days)') }}</label><input v-model="conv.payment_terms_days" type="number" min="0" max="365" dir="ltr" style="width:120px" placeholder="30" /></div>
              <div class="field" style="margin:0"><label>{{ tt('حالة الفاتورة','Invoice status') }}</label><UiSelect v-model="conv.status" style="width:140px"><option value="sent">{{ tt('صادرة','Issued') }}</option><option value="draft">{{ tt('مسودّة','Draft') }}</option></UiSelect></div>
              <button class="btn" :disabled="saving" @click="convert"><i class="ti ti-check" /> {{ tt('إنشاء الفاتورة','Create invoice') }}</button>
              <button class="btn ghost" @click="converting = false">{{ tt('إغلاق','Close') }}</button>
            </div>
            <small class="muted">{{ tt('سيُنقل العميل والبنود والإجماليات كما هي، ويُعلَّم العرض محوّل لفاتورة.','Customer, lines and totals carry over; the quote is marked converted.') }}</small>
          </div>

          <hr style="border:none;border-top:1px solid var(--line);margin:16px 0">
          <div class="grid grid-3">
            <div>
              <small class="muted">{{ tt('العميل','Customer') }}</small><br />
              <b v-if="q.customer"><NuxtLink :to="`/crm/customers/${q.customer.id}`" style="color:var(--info)">{{ q.customer.name }}</NuxtLink></b><b v-else>—</b>
              <div v-if="q.customer" class="muted" style="font-size:12px">{{ [q.customer.phone, q.customer.email].filter(Boolean).join(' · ') }}<br />{{ [q.customer.address, q.customer.city].filter(Boolean).join('، ') }}</div>
            </div>
            <div><small class="muted">{{ tt('مرجع العميل','Customer ref.') }}</small><br /><b dir="ltr">{{ q.reference || '—' }}</b><br /><small class="muted">{{ tt('أُرسل','Sent') }}</small><br /><small>{{ q.sent_at ? f.dt(q.sent_at) : '—' }}</small></div>
            <div><small class="muted">{{ tt('قُبل','Accepted') }}</small><br /><small>{{ q.accepted_at ? f.dt(q.accepted_at) : '—' }}</small><br /><small class="muted">{{ tt('الشروط','Terms') }}</small><br /><small>{{ q.terms || '—' }}</small></div>
          </div>
        </div>

        <div class="card">
          <div class="card-title"><i class="ti ti-list-details" /> {{ tt('البنود','Line items') }} ({{ q.items.length }})</div>
          <table class="table">
            <thead><tr><th>#</th><th>{{ tt('الوصف','Description') }}</th><th>{{ tt('الكمية','Qty') }}</th><th>{{ tt('الوحدة','Unit') }}</th><th>{{ tt('سعر الوحدة','Unit price') }}</th><th>{{ tt('خصم','Disc.') }}</th><th>{{ tt('الإجمالي','Total') }}</th></tr></thead>
            <tbody><tr v-for="(l, i) in q.items" :key="l.id"><td class="muted">{{ i + 1 }}</td><td>{{ l.description }}<small v-if="l.item?.sku" class="muted" dir="ltr"> ({{ l.item.sku }})</small></td><td class="num">{{ f.qty(l.qty) }}</td><td>{{ l.unit || '—' }}</td><td class="num">{{ f.money(l.unit_price) }}</td><td class="num">{{ f.money(l.discount) }}</td><td class="num"><b>{{ f.money(l.total) }}</b></td></tr></tbody>
          </table>
          <div class="totals-box" style="margin-right:auto;max-width:340px">
            <div class="row between"><span>{{ tt('المجموع','Subtotal') }}</span><b>{{ f.money(q.subtotal) }}</b></div>
            <div class="row between"><span>{{ tt('الخصم','Discount') }}<small v-if="q.discount_type === 'percent'" class="muted"> ({{ +q.discount_value }}%)</small></span><b>- {{ f.money(q.discount) }}</b></div>
            <div class="row between"><span>{{ tt('الضريبة','Tax') }} ({{ q.tax_rate }}%)</span><b>{{ f.money(q.tax) }}</b></div>
            <div class="row between total"><span>{{ tt('الإجمالي','Total') }}</span><b>{{ f.money(q.total, true) }}</b></div>
          </div>
          <p v-if="q.notes" class="mt"><small class="muted">{{ tt('ملاحظات للعميل','Customer notes') }}</small><br />{{ q.notes }}</p>
          <p v-if="q.internal_notes" class="mt" style="background:var(--bg);padding:10px;border-radius:10px"><small class="muted"><i class="ti ti-lock" /> {{ tt('ملاحظات داخلية','Internal notes') }}</small><br />{{ q.internal_notes }}</p>
        </div>
      </template>
    </div>

    <DocPrint kind="quote" :doc="q" :company="data.company" />
  </div>
  <div v-else-if="notFound" class="card" style="text-align:center;padding:50px 20px">
    <i class="ti ti-file-off" style="font-size:48px;color:var(--muted)" />
    <h2 class="mt">{{ tt('عرض السعر غير موجود','Quote not found') }}</h2>
    <p class="muted">{{ tt('ربما حُذف أو أن الرابط غير صحيح.','It may have been deleted or the link is wrong.') }}</p>
    <NuxtLink to="/crm/quotes" class="btn ghost mt">{{ tt('العودة إلى عروض الأسعار','Back to quotes') }}</NuxtLink>
  </div>
</template>
