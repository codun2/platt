<script setup lang="ts">
definePageMeta({ title: 'الفاتورة', titleEn: 'Invoice' })
const api = useApi(); const auth = useAuth(); const f = useFmt()
const route = useRoute(); const id = Number(route.params.id); const { tt } = useI18n(); const docP = useDocProfile()

const data = ref<any>(null); const customers = ref<any[]>([])
const editing = ref(route.query.edit === '1'); const paying = ref(false)
const err = ref(''); const msg = ref(''); const saving = ref(false)
const form = reactive<any>(blankDoc('invoice'))
const pay = reactive({ amount: 0, method: 'cash', date: f.today(), reference: '', note: '' })
const inv = computed(() => data.value?.invoice)
usePageTitle(() => inv.value ? `${tt(...docP.profile.value.invoice)} ${inv.value.number}` : null)
const canManage = computed(() => auth.can('crm.manage'))
const dueDays = computed(() => daysUntil(inv.value?.due_date))
const editable = computed(() => inv.value && !['paid', 'cancelled'].includes(inv.value.status))
const print = () => window.print()
const exporting = ref(false)
async function exportPdf() {
  exporting.value = true
  try { await exportDocPdf(document.querySelector('.doc-print') as HTMLElement, docPdfName(inv.value.number, data.value.company?.name)) }
  catch (e: any) { err.value = e.message } finally { exporting.value = false }
}

const notFound = ref(false)
async function load() {
  try { data.value = await api.get(`/app/invoices/${id}`) } catch (e: any) { if (e.status === 404) { notFound.value = true; return } throw e }
  Object.assign(form, docToForm('invoice', data.value.invoice))
  pay.amount = Math.max(0, +data.value.invoice.balance)
}
onMounted(async () => { await load(); customers.value = await api.get('/app/customers', { all: 1 }); if (route.query.print) { await nextTick(); setTimeout(() => window.print(), 400) } })

async function act(fn: () => Promise<any>, ok = tt('تم','Done')) {
  err.value = ''; msg.value = ''; saving.value = true
  try { await fn(); msg.value = ok; await load() } catch (e: any) { err.value = e.message } finally { saving.value = false; window.scrollTo({ top: 0, behavior: 'smooth' }) }
}
const save = () => act(async () => { await api.patch(`/app/invoices/${id}`, docPayload(form)); editing.value = false }, tt('تم حفظ التعديلات','Changes saved'))
const send = () => act(() => api.post(`/app/invoices/${id}/send`), tt('تم إصدار الفاتورة','Invoice issued'))
const cancel = async () => { if (await uiConfirm(tt('إلغاء الفاتورة؟','Cancel this invoice?'))) act(() => api.post(`/app/invoices/${id}/cancel`), tt('تم إلغاء الفاتورة','Invoice cancelled')) }
const doPay = () => act(async () => { await api.post(`/app/invoices/${id}/pay`, pay); paying.value = false; Object.assign(pay, { method: 'cash', reference: '', note: '', date: f.today() }) }, tt('تم تسجيل الدفعة','Payment recorded'))
const delPay = async (p: any) => { if (await uiConfirm(tt(`حذف دفعة ${f.money(p.amount)}؟`,`Delete payment ${f.money(p.amount)}?`))) act(() => api.del(`/app/invoices/${id}/payments/${p.id}`), tt('تم حذف الدفعة','Payment deleted')) }
async function duplicate() { try { const c = await api.post(`/app/invoices/${id}/duplicate`); navigateTo(`/crm/invoices/${c.id}?edit=1`) } catch (e: any) { err.value = e.message } }
async function remove() { if (!await uiConfirm(tt('حذف المسودّة نهائياً؟','Delete draft permanently?'))) return; try { await api.del(`/app/invoices/${id}`); navigateTo('/crm/invoices') } catch (e: any) { err.value = e.message } }
</script>

<template>
  <div v-if="inv">
    <div class="no-print">
      <div class="breadcrumb"><NuxtLink to="/crm/invoices">{{ tt('الفواتير','Invoices') }}</NuxtLink> › <b dir="ltr">{{ inv.number }}</b></div>
      <div v-if="msg" class="alert ok">{{ msg }}</div>
      <div v-if="err" class="alert bad">{{ err }}</div>

      <!-- Edit mode -->
      <div v-if="editing" class="card">
        <DocForm kind="invoice" :form="form" :customers="customers" />
        <div class="row" style="gap:10px;margin-top:18px;border-top:1px solid var(--line);padding-top:16px">
          <button class="btn" :disabled="saving" @click="save"><i class="ti ti-check" /> {{ tt('حفظ التعديلات','Save changes') }}</button>
          <button class="btn ghost" :disabled="saving" @click="editing = false; load()">{{ tt('إلغاء','Cancel') }}</button>
        </div>
      </div>

      <template v-else>
        <div class="grid grid-4" style="margin-bottom:16px">
          <div class="card stat"><div class="ic" style="background:var(--info)"><i class="ti ti-file-invoice" /></div><div><b style="font-size:17px">{{ f.money(inv.total, true) }}</b><small>{{ tt('إجمالي الفاتورة','Invoice total') }}</small></div></div>
          <div class="card stat"><div class="ic" style="background:var(--ok)"><i class="ti ti-cash" /></div><div><b style="font-size:17px">{{ f.money(inv.paid) }}</b><small>{{ tt('المدفوع','Paid') }}</small></div></div>
          <div class="card stat"><div class="ic" :style="`background:${+inv.balance > 0 ? 'var(--primary)' : 'var(--ok)'}`"><i class="ti ti-wallet" /></div><div><b style="font-size:17px">{{ f.money(inv.balance) }}</b><small>{{ tt('المتبقي','Balance') }}</small></div></div>
          <div class="card stat"><div class="ic" :style="`background:${inv.is_overdue ? 'var(--primary)' : 'var(--warn)'}`"><i class="ti ti-calendar-due" /></div><div><b style="font-size:15px">{{ f.d(inv.due_date) }}</b><small v-if="dueDays !== null && ['sent','partial'].includes(inv.status)">{{ dueDays < 0 ? tt(`متأخرة ${-dueDays} يوم`,`${-dueDays}d overdue`) : dueDays === 0 ? tt('تستحق اليوم','Due today') : tt(`بعد ${dueDays} يوم`,`in ${dueDays}d`) }}</small><small v-else>{{ tt('تاريخ الاستحقاق','Due date') }}</small></div></div>
        </div>

        <div class="card">
          <div class="row between" style="align-items:flex-start">
            <div>
              <div class="row" style="gap:10px;align-items:center">
                <h2 style="margin:0" dir="ltr">{{ inv.number }}</h2>
                <span class="badge" :class="invoiceStatus[inv.status]?.cls">{{ invoiceStatus[inv.status]?.label }}</span>
                <span v-if="inv.is_overdue" class="badge bad">{{ tt('متأخرة','Overdue') }}</span>
              </div>
              <p class="muted" style="margin:6px 0 0">{{ inv.subject || tt('فاتورة مبيعات','Sales invoice') }} · {{ f.d(inv.date) }}<span v-if="inv.creator"> · {{ tt('أنشأها','by') }} {{ inv.creator.name }}</span></p>
            </div>
            <div class="row" style="gap:8px;flex-wrap:wrap;justify-content:flex-end">
              <button class="btn ghost sm" @click="print"><i class="ti ti-printer" /> {{ tt('طباعة','Print') }}</button>
              <button class="btn ghost sm" :disabled="exporting" @click="exportPdf"><i class="ti ti-file-type-pdf" /> {{ exporting ? tt('جاري التصدير…','Exporting…') : tt('تصدير PDF','Export PDF') }}</button>
              <template v-if="canManage">
                <button v-if="inv.status === 'draft'" class="btn ok sm" @click="send"><i class="ti ti-send" /> {{ tt('إصدار الفاتورة','Issue invoice') }}</button>
                <button v-if="['draft','sent','partial'].includes(inv.status) && +inv.balance > 0" class="btn info sm" @click="paying = !paying"><i class="ti ti-cash" /> {{ tt('تسجيل دفعة','Add payment') }}</button>
                <button v-if="editable" class="btn ghost sm" @click="editing = true"><i class="ti ti-pencil" /> {{ tt('تعديل','Edit') }}</button>
                <button class="btn ghost sm" @click="duplicate"><i class="ti ti-copy" /> {{ tt('نسخ','Duplicate') }}</button>
                <button v-if="inv.status === 'draft'" class="btn ghost sm" style="color:var(--primary)" @click="remove"><i class="ti ti-trash" /> {{ tt('حذف','Delete') }}</button>
                <button v-else-if="inv.status !== 'cancelled' && +inv.paid === 0" class="btn ghost sm" style="color:var(--primary)" @click="cancel"><i class="ti ti-ban" /> {{ tt('إلغاء','Cancel') }}</button>
              </template>
            </div>
          </div>

          <!-- Payment panel (small, essential action) -->
          <div v-if="paying" class="pay-panel mt">
            <div class="card-title" style="margin-bottom:8px"><i class="ti ti-cash" /> {{ tt('تسجيل دفعة','Add payment') }} — {{ tt('المتبقي','balance') }} {{ f.money(inv.balance, true) }}</div>
            <div class="row" style="align-items:flex-end;gap:10px">
              <div class="field" style="margin:0"><label>{{ tt('المبلغ','Amount') }}</label><input v-model.number="pay.amount" type="number" step="0.001" min="0" dir="ltr" style="width:130px" /></div>
              <div class="field" style="margin:0"><label>{{ tt('الطريقة','Method') }}</label><UiSelect v-model="pay.method" style="width:140px"><option v-for="(l, k) in payMethods" :key="k" :value="k">{{ l }}</option></UiSelect></div>
              <div class="field" style="margin:0"><label>{{ tt('التاريخ','Date') }}</label><input v-model="pay.date" type="date" dir="ltr" /></div>
              <div class="field" style="margin:0"><label>{{ tt('المرجع (حوالة/شيك)','Reference') }}</label><input v-model="pay.reference" dir="ltr" style="width:140px" /></div>
              <div class="field" style="margin:0;flex:1"><label>{{ tt('ملاحظة','Note') }}</label><input v-model="pay.note" /></div>
              <button class="btn" :disabled="saving || !pay.amount" @click="doPay"><i class="ti ti-check" /> {{ tt('حفظ الدفعة','Save payment') }}</button>
              <button class="btn ghost" @click="paying = false">{{ tt('إغلاق','Close') }}</button>
            </div>
          </div>

          <hr style="border:none;border-top:1px solid var(--line);margin:16px 0">
          <div class="grid grid-3">
            <div>
              <small class="muted">{{ tt('العميل','Customer') }}</small><br />
              <b v-if="inv.customer"><NuxtLink :to="`/crm/customers/${inv.customer.id}`" style="color:var(--info)">{{ inv.customer.name }}</NuxtLink></b><b v-else>{{ tt('عميل نقدي','Cash customer') }}</b>
              <div v-if="inv.customer" class="muted" style="font-size:12px">{{ [inv.customer.phone, inv.customer.email].filter(Boolean).join(' · ') }}<br />{{ [inv.customer.address, inv.customer.city].filter(Boolean).join('، ') }}<span v-if="inv.customer.tax_number"><br />{{ tt('الرقم الضريبي','Tax No.') }}: {{ inv.customer.tax_number }}</span></div>
            </div>
            <div>
              <small class="muted">{{ tt('شروط السداد','Payment terms') }}</small><br /><b>{{ inv.payment_terms_days != null ? tt(`${inv.payment_terms_days} يوم`,`${inv.payment_terms_days} days`) : '—' }}</b>
              <br /><small class="muted">{{ tt('المرجع / أمر الشراء','Reference / PO') }}</small><br /><b dir="ltr">{{ inv.reference || '—' }}</b>
            </div>
            <div>
              <small class="muted">{{ tt('مصدر الفاتورة','Source') }}</small><br /><b v-if="inv.quote"><NuxtLink :to="`/crm/quotes/${inv.quote.id}`" style="color:var(--info)" dir="ltr">{{ inv.quote.number }}</NuxtLink></b><b v-else>—</b>
              <br /><small class="muted">{{ tt('تواريخ','Dates') }}</small><br /><small>{{ tt('أُصدرت','Issued') }}: {{ inv.sent_at ? f.dt(inv.sent_at) : '—' }}<br />{{ tt('سُدّدت','Paid') }}: {{ inv.paid_at ? f.dt(inv.paid_at) : '—' }}</small>
            </div>
          </div>
        </div>

        <div class="card">
          <div class="card-title"><i class="ti ti-list-details" /> {{ tt('البنود','Line items') }} ({{ inv.items.length }})</div>
          <DocLinesView :items="inv.items" :mode="docP.profile.value.lineMode" />
          <div class="totals-box" style="margin-right:auto;max-width:340px">
            <div class="row between"><span>{{ tt('المجموع','Subtotal') }}</span><b>{{ f.money(inv.subtotal) }}</b></div>
            <div class="row between"><span>{{ tt('الخصم','Discount') }}<small v-if="inv.discount_type === 'percent'" class="muted"> ({{ +inv.discount_value }}%)</small></span><b>- {{ f.money(inv.discount) }}</b></div>
            <div class="row between"><span>{{ tt('الضريبة','Tax') }} ({{ inv.tax_rate }}%)</span><b>{{ f.money(inv.tax) }}</b></div>
            <div class="row between total"><span>{{ tt('الإجمالي','Total') }}</span><b>{{ f.money(inv.total, true) }}</b></div>
          </div>
          <p v-if="inv.notes" class="mt"><small class="muted">{{ tt('ملاحظات للعميل','Customer notes') }}</small><br />{{ inv.notes }}</p>
          <p v-if="inv.internal_notes" class="mt" style="background:var(--bg);padding:10px;border-radius:10px"><small class="muted"><i class="ti ti-lock" /> {{ tt('ملاحظات داخلية','Internal notes') }}</small><br />{{ inv.internal_notes }}</p>
        </div>

        <div class="card">
          <div class="card-title"><i class="ti ti-cash" /> {{ tt('الدفعات','Payments') }} ({{ inv.payments.length }})</div>
          <table class="table">
            <thead><tr><th>{{ tt('التاريخ','Date') }}</th><th>{{ tt('الطريقة','Method') }}</th><th>{{ tt('المرجع','Reference') }}</th><th>{{ tt('ملاحظة','Note') }}</th><th>{{ tt('بواسطة','By') }}</th><th>{{ tt('المبلغ','Amount') }}</th><th v-if="canManage"></th></tr></thead>
            <tbody><tr v-for="p in inv.payments" :key="p.id"><td>{{ f.d(p.date) }}</td><td>{{ payMethods[p.method] || p.method }}</td><td dir="ltr" style="text-align:right">{{ p.reference || '—' }}</td><td class="muted">{{ p.note || '' }}</td><td>{{ p.user?.name || '—' }}</td><td class="num"><b style="color:var(--ok)">{{ f.money(p.amount) }}</b></td><td v-if="canManage"><button class="btn ghost sm icon" style="color:var(--primary)" @click="delPay(p)"><i class="ti ti-x" /></button></td></tr></tbody>
          </table>
          <p v-if="!inv.payments.length" class="empty">{{ tt('لا توجد دفعات بعد.','No payments yet.') }}</p>
        </div>
      </template>
    </div>

    <DocPrint kind="invoice" :doc="inv" :company="data.company" />
  </div>
  <div v-else-if="notFound" class="card" style="text-align:center;padding:50px 20px">
    <i class="ti ti-file-off" style="font-size:48px;color:var(--muted)" />
    <h2 class="mt">{{ tt('الفاتورة غير موجودة','Invoice not found') }}</h2>
    <p class="muted">{{ tt('ربما حُذفت أو أن الرابط غير صحيح.','It may have been deleted or the link is wrong.') }}</p>
    <NuxtLink to="/crm/invoices" class="btn ghost mt">{{ tt('العودة إلى الفواتير','Back to invoices') }}</NuxtLink>
  </div>
</template>
