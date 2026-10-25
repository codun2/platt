<script setup lang="ts">
definePageMeta({ title: 'فواتير المبيعات', titleEn: 'Sales Invoices' })
const api = useApi(); const auth = useAuth(); const f = useFmt(); const route = useRoute(); const { tt } = useI18n()
const page = ref<any>(null)
const filt = reactive({ q: '', status: '', from: '', to: '', unpaid: false })
const view = ref<any>(null); const company = ref<any>(null)
const pay = reactive({ amount: 0, method: 'cash', note: '' })
const printing = ref(false)

async function load(p: any = 1) { const n = typeof p === 'number' ? p : 1; page.value = await api.get('/app/pos/sales', { ...filt, unpaid: filt.unpaid ? 1 : '', page: n }) }
async function show(id: number) { const r = await api.get(`/app/pos/sales/${id}`); view.value = r.sale; company.value = r.company; pay.amount = Math.max(0, Number(r.sale.total) - Number(r.sale.paid)) }
onMounted(async () => {
  await load()
  if (route.query.open) await show(Number(route.query.open))
  if (route.query.print) { await show(Number(route.query.print)); printing.value = true; await nextTick(); setTimeout(() => window.print(), 300) }
})
async function doPay() { try { await api.post(`/app/pos/sales/${view.value.id}/pay`, pay); await show(view.value.id); await load() } catch (e: any) { uiAlert(e.message) } }
async function cancel() { if (!await uiConfirm(tt('إلغاء الفاتورة وإرجاع الكميات للمخزون؟','Cancel invoice and return quantities to stock?'))) return; try { await api.post(`/app/pos/sales/${view.value.id}/cancel`); await show(view.value.id); await load() } catch (e: any) { uiAlert(e.message) } }
function print() { printing.value = true; nextTick(() => { window.print(); setTimeout(() => (printing.value = false), 500) }) }
</script>

<template>
  <div>
    <div class="breadcrumb no-print"><NuxtLink to="/pos">{{ tt('نقطة البيع','Point of Sale') }}</NuxtLink> › <b>{{ tt('الفواتير','Invoices') }}</b></div>
    <div class="card no-print">
      <div class="row" style="margin-bottom:14px">
        <input v-model="filt.q" :placeholder="tt('رقم / عميل / هاتف','Number / customer / phone')" style="padding:8px 12px;border:1px solid var(--line);border-radius:10px" @keyup.enter="load" />
        <select v-model="filt.status" style="padding:8px 12px;border:1px solid var(--line);border-radius:10px" @change="load"><option value="">{{ tt('كل الحالات','All statuses') }}</option><option value="completed">{{ tt('مكتملة','Completed') }}</option><option value="cancelled">{{ tt('ملغاة','Cancelled') }}</option></select>
        <input v-model="filt.from" type="date" style="padding:7px 10px;border:1px solid var(--line);border-radius:10px" @change="load" />
        <input v-model="filt.to" type="date" style="padding:7px 10px;border:1px solid var(--line);border-radius:10px" @change="load" />
        <label class="switch"><input v-model="filt.unpaid" type="checkbox" @change="load" /><span /> {{ tt('غير مسددة','Unpaid') }}</label>
        <button class="btn ghost" @click="load"><i class="ti ti-search" /></button>
        <NuxtLink to="/pos" class="btn" style="margin-right:auto"><i class="ti ti-cash-register" /> {{ tt('شاشة البيع','Sell screen') }}</NuxtLink>
      </div>
      <table class="table">
        <thead><tr><th>{{ tt('الرقم','Number') }}</th><th>{{ tt('التاريخ','Date') }}</th><th>{{ tt('العميل','Customer') }}</th><th>{{ tt('بنود','Items') }}</th><th>{{ tt('الإجمالي','Total') }}</th><th>{{ tt('المدفوع','Paid') }}</th><th>{{ tt('المتبقي','Balance') }}</th><th>{{ tt('الدفع','Payment') }}</th><th>{{ tt('الحالة','Status') }}</th><th>{{ tt('البائع','Seller') }}</th><th></th></tr></thead>
        <tbody>
          <tr v-for="s in page?.data" :key="s.id">
            <td><a href="#" style="color:var(--info);font-weight:700" @click.prevent="show(s.id)">{{ s.number }}</a><span v-if="s.offline" class="badge muted" style="margin-inline-start:4px" :title="s.client_ref"><i class="ti ti-wifi-off" /> {{ tt('بيع دون اتصال', 'Offline sale') }}</span><span v-if="s.stock_short" class="badge warn" style="margin-inline-start:4px">{{ tt('بيع بدون رصيد كافٍ', 'Sold beyond stock') }}</span></td><td>{{ f.d(s.date) }}</td><td>{{ s.customer?.name || tt('نقدي','Cash') }}</td><td>{{ s.items_count }}</td>
            <td class="num"><b>{{ f.money(s.total) }}</b></td><td class="num">{{ f.money(s.paid) }}</td><td class="num" :style="Number(s.total) - Number(s.paid) > 0 ? 'color:var(--primary);font-weight:700' : ''">{{ f.money(Number(s.total) - Number(s.paid)) }}</td>
            <td>{{ f.methodLabel(s.payment_method) }}</td><td><span class="badge" :class="s.status === 'completed' ? 'ok' : 'bad'">{{ s.status === 'completed' ? tt('مكتملة','Completed') : tt('ملغاة','Cancelled') }}</span></td><td>{{ s.creator?.name }}</td>
            <td><button class="btn ghost sm icon" @click="show(s.id)"><i class="ti ti-eye" /></button></td>
          </tr>
        </tbody>
      </table>
      <UiPager :p="page" @go="load" />
      <p v-if="page && !page.data.length" class="empty">{{ tt('لا توجد فواتير.','No invoices.') }}</p>
      <p class="muted mt" style="font-size:12px">{{ tt('الإجمالي','Total') }}: {{ page?.total ?? 0 }}</p>
    </div>

    <!-- Invoice (modal on screen, plain sheet when printing) -->
    <UiModal v-if="view" :title="`${tt('فاتورة','Invoice')} ${view.number}`" lg @close="view = null">
      <div class="invoice">
        <div class="row between"><div><h2 style="font-size:20px">{{ company?.name }}</h2><small class="muted">{{ company?.phone }} {{ company?.email }}</small></div><div style="text-align:left"><b style="font-size:16px">{{ view.number }}</b><br /><small>{{ f.d(view.date) }} · {{ f.dt(view.created_at).slice(11) }}</small></div></div>
        <div class="row between mt"><span>{{ tt('العميل:','Customer:') }} <b>{{ view.customer?.name || tt('نقدي','Cash') }}</b> <small class="muted" dir="ltr">{{ view.customer?.phone }}</small></span><span class="badge" :class="view.status === 'completed' ? 'ok' : 'bad'">{{ view.status === 'completed' ? tt('مكتملة','Completed') : tt('ملغاة','Cancelled') }}</span></div>
        <table class="table mt"><thead><tr><th>#</th><th>{{ tt('الصنف','Item') }}</th><th>{{ tt('الكمية','Qty') }}</th><th>{{ tt('السعر','Price') }}</th><th>{{ tt('خصم','Disc.') }}</th><th>{{ tt('الإجمالي','Total') }}</th></tr></thead>
          <tbody><tr v-for="(l, i) in view.items" :key="l.id"><td>{{ i + 1 }}</td><td>{{ l.name }}</td><td class="num">{{ f.qty(l.qty) }}</td><td class="num">{{ f.money(l.unit_price) }}</td><td class="num">{{ f.money(l.discount) }}</td><td class="num">{{ f.money(l.total) }}</td></tr></tbody></table>
        <div class="inv-totals">
          <div class="row between"><span>{{ tt('المجموع','Subtotal') }}</span><b>{{ f.money(view.subtotal) }}</b></div>
          <div class="row between"><span>{{ tt('خصم','Discount') }}</span><b>{{ f.money(view.discount) }}</b></div>
          <div class="row between"><span>{{ tt('ضريبة','Tax') }} {{ view.tax_rate }}%</span><b>{{ f.money(view.tax) }}</b></div>
          <div class="row between" style="font-size:17px;color:var(--primary)"><span>{{ tt('الإجمالي','Total') }}</span><b>{{ f.money(view.total, true) }}</b></div>
          <div class="row between"><span>{{ tt('المدفوع','Paid') }} ({{ f.methodLabel(view.payment_method) }})</span><b>{{ f.money(view.paid) }}</b></div>
          <div v-if="Number(view.total) - Number(view.paid) > 0" class="row between" style="color:var(--primary)"><span>{{ tt('المتبقي','Balance') }}</span><b>{{ f.money(Number(view.total) - Number(view.paid)) }}</b></div>
        </div>
        <p v-if="view.note" class="muted mt">{{ view.note }}</p>
        <p class="muted mt" style="text-align:center;font-size:12px">{{ tt('شكراً لتعاملكم معنا','Thank you for your business') }}</p>
      </div>
      <template #footer>
        <div class="no-print row" style="width:100%;justify-content:space-between">
          <div class="row"><button class="btn ghost" @click="print"><i class="ti ti-printer" /> {{ tt('طباعة','Print') }}</button><button v-if="view.status === 'completed' && auth.can('pos.manage')" class="btn ghost" style="color:var(--primary)" @click="cancel">{{ tt('إلغاء الفاتورة','Cancel invoice') }}</button></div>
          <div v-if="view.status === 'completed' && Number(view.total) - Number(view.paid) > 0" class="row">
            <input v-model.number="pay.amount" type="number" step="0.001" dir="ltr" style="width:110px;padding:7px;border:1px solid var(--line);border-radius:8px" />
            <select v-model="pay.method" style="padding:7px;border:1px solid var(--line);border-radius:8px"><option value="cash">{{ tt('نقدي','Cash') }}</option><option value="card">{{ tt('بطاقة','Card') }}</option><option value="transfer">{{ tt('تحويل','Transfer') }}</option></select>
            <button class="btn info" @click="doPay">{{ tt('تسجيل دفعة','Add payment') }}</button>
          </div>
        </div>
      </template>
    </UiModal>
  </div>
</template>

<style>
.inv-totals { max-width: 320px; margin-right: auto; margin-top: 10px; display: flex; flex-direction: column; gap: 4px; }
@media print {
  .no-print, .sidebar, .topbar, .footer, .modal header, .modal footer { display: none !important; }
  .content { padding: 0 !important; }
  .modal-bg { position: static; background: #fff; display: block; padding: 0; }
  .modal { max-width: none; max-height: none; box-shadow: none; border-radius: 0; }
  body { background: #fff; }
}
</style>
