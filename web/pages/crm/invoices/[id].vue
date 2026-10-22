<script setup lang="ts">
definePageMeta({ title: 'الفاتورة' })
const api = useApi(); const auth = useAuth(); const f = useFmt()
const route = useRoute(); const id = Number(route.params.id)

const data = ref<any>(null); const customers = ref<any[]>([])
const editing = ref(route.query.edit === '1'); const paying = ref(false)
const err = ref(''); const msg = ref(''); const saving = ref(false)
const form = reactive<any>(blankDoc('invoice'))
const pay = reactive({ amount: 0, method: 'cash', date: f.today(), reference: '', note: '' })
const inv = computed(() => data.value?.invoice)
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

async function act(fn: () => Promise<any>, ok = 'تم') {
  err.value = ''; msg.value = ''; saving.value = true
  try { await fn(); msg.value = ok; await load() } catch (e: any) { err.value = e.message } finally { saving.value = false; window.scrollTo({ top: 0, behavior: 'smooth' }) }
}
const save = () => act(async () => { await api.patch(`/app/invoices/${id}`, docPayload(form)); editing.value = false }, 'تم حفظ التعديلات')
const send = () => act(() => api.post(`/app/invoices/${id}/send`), 'تم إصدار الفاتورة')
const cancel = () => { if (confirm('إلغاء الفاتورة؟')) act(() => api.post(`/app/invoices/${id}/cancel`), 'تم إلغاء الفاتورة') }
const doPay = () => act(async () => { await api.post(`/app/invoices/${id}/pay`, pay); paying.value = false; Object.assign(pay, { method: 'cash', reference: '', note: '', date: f.today() }) }, 'تم تسجيل الدفعة')
const delPay = (p: any) => { if (confirm(`حذف دفعة ${f.money(p.amount)}؟`)) act(() => api.del(`/app/invoices/${id}/payments/${p.id}`), 'تم حذف الدفعة') }
async function duplicate() { try { const c = await api.post(`/app/invoices/${id}/duplicate`); navigateTo(`/crm/invoices/${c.id}?edit=1`) } catch (e: any) { err.value = e.message } }
async function remove() { if (!confirm('حذف المسودّة نهائياً؟')) return; try { await api.del(`/app/invoices/${id}`); navigateTo('/crm/invoices') } catch (e: any) { err.value = e.message } }
</script>

<template>
  <div v-if="inv">
    <div class="no-print">
      <div class="breadcrumb"><NuxtLink to="/crm/invoices">الفواتير</NuxtLink> › <b dir="ltr">{{ inv.number }}</b></div>
      <div v-if="msg" class="alert ok">{{ msg }}</div>
      <div v-if="err" class="alert bad">{{ err }}</div>

      <!-- Edit mode -->
      <div v-if="editing" class="card">
        <DocForm kind="invoice" :form="form" :customers="customers" />
        <div class="row" style="gap:10px;margin-top:18px;border-top:1px solid var(--line);padding-top:16px">
          <button class="btn" :disabled="saving" @click="save"><i class="ti ti-check" /> حفظ التعديلات</button>
          <button class="btn ghost" :disabled="saving" @click="editing = false; load()">إلغاء</button>
        </div>
      </div>

      <template v-else>
        <div class="grid grid-4" style="margin-bottom:16px">
          <div class="card stat"><div class="ic" style="background:var(--info)"><i class="ti ti-file-invoice" /></div><div><b style="font-size:17px">{{ f.money(inv.total, true) }}</b><small>إجمالي الفاتورة</small></div></div>
          <div class="card stat"><div class="ic" style="background:var(--ok)"><i class="ti ti-cash" /></div><div><b style="font-size:17px">{{ f.money(inv.paid) }}</b><small>المدفوع</small></div></div>
          <div class="card stat"><div class="ic" :style="`background:${+inv.balance > 0 ? 'var(--primary)' : 'var(--ok)'}`"><i class="ti ti-wallet" /></div><div><b style="font-size:17px">{{ f.money(inv.balance) }}</b><small>المتبقي</small></div></div>
          <div class="card stat"><div class="ic" :style="`background:${inv.is_overdue ? 'var(--primary)' : 'var(--warn)'}`"><i class="ti ti-calendar-due" /></div><div><b style="font-size:15px">{{ f.d(inv.due_date) }}</b><small v-if="dueDays !== null && ['sent','partial'].includes(inv.status)">{{ dueDays < 0 ? `متأخرة ${-dueDays} يوم` : dueDays === 0 ? 'تستحق اليوم' : `بعد ${dueDays} يوم` }}</small><small v-else>تاريخ الاستحقاق</small></div></div>
        </div>

        <div class="card">
          <div class="row between" style="align-items:flex-start">
            <div>
              <div class="row" style="gap:10px;align-items:center">
                <h2 style="margin:0" dir="ltr">{{ inv.number }}</h2>
                <span class="badge" :class="invoiceStatus[inv.status]?.cls">{{ invoiceStatus[inv.status]?.label }}</span>
                <span v-if="inv.is_overdue" class="badge bad">متأخرة</span>
              </div>
              <p class="muted" style="margin:6px 0 0">{{ inv.subject || 'فاتورة مبيعات' }} · {{ f.d(inv.date) }}<span v-if="inv.creator"> · أنشأها {{ inv.creator.name }}</span></p>
            </div>
            <div class="row" style="gap:8px;flex-wrap:wrap;justify-content:flex-end">
              <button class="btn ghost sm" @click="print"><i class="ti ti-printer" /> طباعة</button>
              <button class="btn ghost sm" :disabled="exporting" @click="exportPdf"><i class="ti ti-file-type-pdf" /> {{ exporting ? 'جاري التصدير…' : 'تصدير PDF' }}</button>
              <template v-if="canManage">
                <button v-if="inv.status === 'draft'" class="btn ok sm" @click="send"><i class="ti ti-send" /> إصدار الفاتورة</button>
                <button v-if="['draft','sent','partial'].includes(inv.status) && +inv.balance > 0" class="btn info sm" @click="paying = !paying"><i class="ti ti-cash" /> تسجيل دفعة</button>
                <button v-if="editable" class="btn ghost sm" @click="editing = true"><i class="ti ti-pencil" /> تعديل</button>
                <button class="btn ghost sm" @click="duplicate"><i class="ti ti-copy" /> نسخ</button>
                <button v-if="inv.status === 'draft'" class="btn ghost sm" style="color:var(--primary)" @click="remove"><i class="ti ti-trash" /> حذف</button>
                <button v-else-if="inv.status !== 'cancelled' && +inv.paid === 0" class="btn ghost sm" style="color:var(--primary)" @click="cancel"><i class="ti ti-ban" /> إلغاء</button>
              </template>
            </div>
          </div>

          <!-- Payment panel (small, essential action) -->
          <div v-if="paying" class="pay-panel mt">
            <div class="card-title" style="margin-bottom:8px"><i class="ti ti-cash" /> تسجيل دفعة — المتبقي {{ f.money(inv.balance, true) }}</div>
            <div class="row" style="align-items:flex-end;gap:10px">
              <div class="field" style="margin:0"><label>المبلغ</label><input v-model.number="pay.amount" type="number" step="0.001" min="0" dir="ltr" style="width:130px" /></div>
              <div class="field" style="margin:0"><label>الطريقة</label><UiSelect v-model="pay.method" style="width:140px"><option v-for="(l, k) in payMethods" :key="k" :value="k">{{ l }}</option></UiSelect></div>
              <div class="field" style="margin:0"><label>التاريخ</label><input v-model="pay.date" type="date" dir="ltr" /></div>
              <div class="field" style="margin:0"><label>المرجع (حوالة/شيك)</label><input v-model="pay.reference" dir="ltr" style="width:140px" /></div>
              <div class="field" style="margin:0;flex:1"><label>ملاحظة</label><input v-model="pay.note" /></div>
              <button class="btn" :disabled="saving || !pay.amount" @click="doPay"><i class="ti ti-check" /> حفظ الدفعة</button>
              <button class="btn ghost" @click="paying = false">إغلاق</button>
            </div>
          </div>

          <hr style="border:none;border-top:1px solid var(--line);margin:16px 0">
          <div class="grid grid-3">
            <div>
              <small class="muted">العميل</small><br />
              <b v-if="inv.customer"><NuxtLink :to="`/crm/customers/${inv.customer.id}`" style="color:var(--info)">{{ inv.customer.name }}</NuxtLink></b><b v-else>عميل نقدي</b>
              <div v-if="inv.customer" class="muted" style="font-size:12px">{{ [inv.customer.phone, inv.customer.email].filter(Boolean).join(' · ') }}<br />{{ [inv.customer.address, inv.customer.city].filter(Boolean).join('، ') }}<span v-if="inv.customer.tax_number"><br />الرقم الضريبي: {{ inv.customer.tax_number }}</span></div>
            </div>
            <div>
              <small class="muted">شروط السداد</small><br /><b>{{ inv.payment_terms_days != null ? `${inv.payment_terms_days} يوم` : '—' }}</b>
              <br /><small class="muted">المرجع / أمر الشراء</small><br /><b dir="ltr">{{ inv.reference || '—' }}</b>
            </div>
            <div>
              <small class="muted">مصدر الفاتورة</small><br /><b v-if="inv.quote"><NuxtLink :to="`/crm/quotes/${inv.quote.id}`" style="color:var(--info)" dir="ltr">{{ inv.quote.number }}</NuxtLink></b><b v-else>—</b>
              <br /><small class="muted">تواريخ</small><br /><small>أُصدرت: {{ inv.sent_at ? f.dt(inv.sent_at) : '—' }}<br />سُدّدت: {{ inv.paid_at ? f.dt(inv.paid_at) : '—' }}</small>
            </div>
          </div>
        </div>

        <div class="card">
          <div class="card-title"><i class="ti ti-list-details" /> البنود ({{ inv.items.length }})</div>
          <table class="table">
            <thead><tr><th>#</th><th>الوصف</th><th>الكمية</th><th>الوحدة</th><th>سعر الوحدة</th><th>خصم</th><th>الإجمالي</th></tr></thead>
            <tbody><tr v-for="(l, i) in inv.items" :key="l.id"><td class="muted">{{ i + 1 }}</td><td>{{ l.description }}<small v-if="l.item?.sku" class="muted" dir="ltr"> ({{ l.item.sku }})</small></td><td class="num">{{ f.qty(l.qty) }}</td><td>{{ l.unit || '—' }}</td><td class="num">{{ f.money(l.unit_price) }}</td><td class="num">{{ f.money(l.discount) }}</td><td class="num"><b>{{ f.money(l.total) }}</b></td></tr></tbody>
          </table>
          <div class="totals-box" style="margin-right:auto;max-width:340px">
            <div class="row between"><span>المجموع</span><b>{{ f.money(inv.subtotal) }}</b></div>
            <div class="row between"><span>الخصم<small v-if="inv.discount_type === 'percent'" class="muted"> ({{ +inv.discount_value }}%)</small></span><b>- {{ f.money(inv.discount) }}</b></div>
            <div class="row between"><span>الضريبة ({{ inv.tax_rate }}%)</span><b>{{ f.money(inv.tax) }}</b></div>
            <div class="row between total"><span>الإجمالي</span><b>{{ f.money(inv.total, true) }}</b></div>
          </div>
          <p v-if="inv.notes" class="mt"><small class="muted">ملاحظات للعميل</small><br />{{ inv.notes }}</p>
          <p v-if="inv.internal_notes" class="mt" style="background:var(--bg);padding:10px;border-radius:10px"><small class="muted"><i class="ti ti-lock" /> ملاحظات داخلية</small><br />{{ inv.internal_notes }}</p>
        </div>

        <div class="card">
          <div class="card-title"><i class="ti ti-cash" /> الدفعات ({{ inv.payments.length }})</div>
          <table class="table">
            <thead><tr><th>التاريخ</th><th>الطريقة</th><th>المرجع</th><th>ملاحظة</th><th>بواسطة</th><th>المبلغ</th><th v-if="canManage"></th></tr></thead>
            <tbody><tr v-for="p in inv.payments" :key="p.id"><td>{{ f.d(p.date) }}</td><td>{{ payMethods[p.method] || p.method }}</td><td dir="ltr" style="text-align:right">{{ p.reference || '—' }}</td><td class="muted">{{ p.note || '' }}</td><td>{{ p.user?.name || '—' }}</td><td class="num"><b style="color:var(--ok)">{{ f.money(p.amount) }}</b></td><td v-if="canManage"><button class="btn ghost sm icon" style="color:var(--primary)" @click="delPay(p)"><i class="ti ti-x" /></button></td></tr></tbody>
          </table>
          <p v-if="!inv.payments.length" class="empty">لا توجد دفعات بعد.</p>
        </div>
      </template>
    </div>

    <DocPrint kind="invoice" :doc="inv" :company="data.company" />
  </div>
  <div v-else-if="notFound" class="card" style="text-align:center;padding:50px 20px">
    <i class="ti ti-file-off" style="font-size:48px;color:var(--muted)" />
    <h2 class="mt">الفاتورة غير موجودة</h2>
    <p class="muted">ربما حُذفت أو أن الرابط غير صحيح.</p>
    <NuxtLink to="/crm/invoices" class="btn ghost mt">العودة إلى الفواتير</NuxtLink>
  </div>
</template>
