<script setup lang="ts">
definePageMeta({ title: 'الطلب', titleEn: 'Order' })
const api = useApi(); const auth = useAuth(); const f = useFmt(); const route = useRoute()
const id = route.params.id as string
const d = ref<any>(null); const err = ref(''); const msg = ref(''); const busy = ref(false)
const panel = ref<'' | 'deliver' | 'deposit' | 'cancel'>(route.query.deliver ? 'deliver' : '')
const pay = reactive({ amount: null as number | null, method: 'cash', note: '' })
const cancel = reactive({ reason: '', refund: true, method: 'cash' })
const printMode = ref<'ticket' | 'receipt'>('ticket')
const o = computed(() => d.value?.order)
usePageTitle(() => o.value ? `${tt('طلب', 'Order')} ${o.value.number}` : null)
const remaining = computed(() => o.value ? Math.max(0, +o.value.total - +o.value.paid) : 0)
const flow = computed(() => o.value?.fulfilment === 'delivery' ? ['new', 'confirmed', 'preparing', 'ready', 'out', 'delivered'] : ['new', 'confirmed', 'preparing', 'ready', 'delivered'])
const step = computed(() => flow.value.indexOf(o.value?.status))
const open = computed(() => o.value && !['delivered', 'cancelled'].includes(o.value.status))
const name = computed(() => o.value?.customer?.name || o.value?.customer_name)
const phone = computed(() => o.value?.customer?.phone || o.value?.customer_phone)

async function load() { d.value = await api.get(`/app/orders/${id}`) }
onMounted(async () => {
  await load()
  if (panel.value === 'deliver') pay.amount = remaining.value
  if (route.query.print) { printMode.value = route.query.print === 'receipt' ? 'receipt' : 'ticket'; nextTick(() => setTimeout(() => window.print(), 300)) }
})
function openPanel(p: typeof panel.value) { panel.value = p; err.value = ''; msg.value = ''; Object.assign(pay, { amount: p === 'deliver' ? remaining.value : null, method: 'cash', note: '' }) }
async function run(fn: () => Promise<any>, ok: string) {
  err.value = ''; msg.value = ''; busy.value = true
  try { d.value = await fn(); msg.value = ok; panel.value = '' } catch (e: any) { err.value = e.message } finally { busy.value = false }
}
const setStatus = (s: string) => run(() => api.post(`/app/orders/${id}/status`, { status: s }), tt('تم تحديث الحالة', 'Status updated'))
const deliver = () => run(() => api.post(`/app/orders/${id}/deliver`, { paid: pay.amount || 0, method: pay.method }), tt('تم التسليم وإصدار الفاتورة', 'Delivered — invoice issued'))
const deposit = () => run(() => api.post(`/app/orders/${id}/deposit`, { amount: pay.amount, method: pay.method, note: pay.note || null }), tt('تم تسجيل العربون', 'Deposit recorded'))
const doCancel = () => run(() => api.post(`/app/orders/${id}/cancel`, cancel), tt('تم إلغاء الطلب', 'Order cancelled'))
function print(mode: 'ticket' | 'receipt') { printMode.value = mode; nextTick(() => window.print()) }

function waPhone(p: string) { let x = (p || '').replace(/\D/g, ''); if (x.startsWith('00')) x = x.slice(2); else if (x.startsWith('0')) x = '966' + x.slice(1); else if (x.length <= 9) x = '966' + x; return x }
const waText = computed(() => {
  if (!o.value) return ''
  const co = d.value.company.name
  if (o.value.status === 'ready') return tt(`مرحباً ${name.value}، طلبكم رقم ${o.value.number} جاهز${o.value.fulfilment === 'pickup' ? ' للاستلام' : ' وسيخرج للتوصيل قريباً'}. المتبقي ${f.money(remaining.value)}. شكراً لكم — ${co}`, `Hi ${name.value}, your order ${o.value.number} is ready. Balance ${f.money(remaining.value)}. Thank you — ${co}`)
  if (o.value.status === 'out') return tt(`مرحباً ${name.value}، طلبكم رقم ${o.value.number} في الطريق إليكم${o.value.driver ? ' مع ' + o.value.driver : ''}. المتبقي ${f.money(remaining.value)}. — ${co}`, `Hi ${name.value}, your order ${o.value.number} is on its way. Balance ${f.money(remaining.value)}. — ${co}`)
  return tt(`مرحباً ${name.value}، تم تسجيل طلبكم رقم ${o.value.number} للتسليم ${dueLabel(o.value.due_at)}. الإجمالي ${f.money(o.value.total)}، المدفوع ${f.money(o.value.paid)}. شكراً لكم — ${co}`, `Hi ${name.value}, your order ${o.value.number} is booked for ${dueLabel(o.value.due_at)}. Total ${f.money(o.value.total)}, paid ${f.money(o.value.paid)}. — ${co}`)
})
function sendWa() { window.open(`https://wa.me/${waPhone(phone.value)}?text=${encodeURIComponent(waText.value)}`, '_blank') }
</script>

<template>
  <div v-if="o">
    <div class="no-print">
      <div class="breadcrumb"><NuxtLink to="/orders">{{ tt('الطلبات المسبقة', 'Pre-orders') }}</NuxtLink> › <b dir="ltr">{{ o.number }}</b></div>
      <div v-if="msg" class="alert ok">{{ msg }}</div>
      <div v-if="err && !panel" class="alert bad">{{ err }}</div>

      <div class="card">
        <div class="row between" style="flex-wrap:wrap;gap:10px">
          <div>
            <h2 style="margin:0">{{ name }} <span class="badge" :class="preOrderStatus[o.status].cls">{{ preOrderStatus[o.status].label }}</span></h2>
            <div class="muted" style="margin-top:4px"><span dir="ltr">{{ phone }}</span> · {{ preOrderChannel[o.channel]?.label }} · <b :style="{ color: isLate(o) ? 'var(--primary)' : 'var(--ink)' }"><i class="ti ti-clock" /> {{ dueLabel(o.due_at) }}</b><span v-if="isLate(o)" style="color:var(--primary)"> ({{ tt('متأخر', 'late') }})</span></div>
          </div>
          <div class="row" style="gap:6px;flex-wrap:wrap">
            <button class="btn ghost sm" @click="print('ticket')"><i class="ti ti-chef-hat" /> {{ tt('تذكرة التحضير', 'Prep ticket') }}</button>
            <button class="btn ghost sm" @click="print('receipt')"><i class="ti ti-printer" /> {{ tt('إيصال العميل', 'Customer receipt') }}</button>
            <button v-if="phone" class="btn ghost sm" style="color:#16a34a" @click="sendWa"><i class="ti ti-brand-whatsapp" /> {{ tt('إرسال للعميل', 'Send to customer') }}</button>
            <NuxtLink v-if="open" :to="`/orders/${id}/edit`" class="btn ghost sm"><i class="ti ti-pencil" /> {{ tt('تعديل', 'Edit') }}</NuxtLink>
          </div>
        </div>

        <!-- progress -->
        <div v-if="o.status !== 'cancelled'" class="stepper">
          <div v-for="(s, i) in flow" :key="s" class="st" :class="{ done: i <= step, cur: i === step }"><span class="dot"><i :class="`ti ${preOrderStatus[s].icon}`" /></span><small>{{ preOrderStatus[s].label }}</small></div>
        </div>
        <div v-else class="alert bad" style="margin-top:12px">{{ tt('ملغى', 'Cancelled') }} <span v-if="o.cancel_reason">— {{ o.cancel_reason }}</span></div>

        <div v-if="open" class="row" style="gap:8px;flex-wrap:wrap;margin-top:14px">
          <template v-for="s in flow.slice(step + 1, step + 2)" :key="s">
            <button v-if="s !== 'delivered'" class="btn" :disabled="busy" @click="setStatus(s)"><i :class="`ti ${preOrderStatus[s].icon}`" /> {{ tt('نقل إلى:', 'Move to:') }} {{ preOrderStatus[s].label }}</button>
          </template>
          <button class="btn ok" @click="openPanel('deliver')"><i class="ti ti-checks" /> {{ tt('تسليم الطلب وإصدار الفاتورة', 'Hand over & invoice') }}</button>
          <button class="btn ghost" @click="openPanel('deposit')"><i class="ti ti-cash" /> {{ tt('تسجيل عربون / دفعة', 'Add deposit') }}</button>
          <button v-if="auth.can('orders.manage')" class="btn ghost" style="color:var(--primary)" @click="openPanel('cancel')"><i class="ti ti-x" /> {{ tt('إلغاء الطلب', 'Cancel order') }}</button>
        </div>

        <div v-if="panel" class="panel">
          <div v-if="err" class="alert bad">{{ err }}</div>
          <template v-if="panel === 'deliver'">
            <p style="margin-top:0">{{ tt('تُصدر فاتورة بقيمة', 'An invoice of') }} <b>{{ f.money(o.total, true) }}</b>{{ tt('، ويُخصم المخزون، ويُحتسب العربون', ' is issued, stock is deducted and the deposit of') }} <b>{{ f.money(o.paid) }}</b>{{ tt('. المتبقي على العميل:', ' applied. Balance:') }} <b style="color:var(--primary)">{{ f.money(remaining) }}</b></p>
            <div class="grid grid-3">
              <div class="field"><label>{{ tt('المبلغ المحصّل الآن', 'Collected now') }}</label><input v-model.number="pay.amount" type="number" min="0" step="0.001" dir="ltr" /><small class="muted">{{ tt('اتركه 0 ليبقى المتبقي ديناً على العميل', 'Leave 0 to keep the balance on account') }}</small></div>
              <div class="field"><label>{{ tt('الطريقة', 'Method') }}</label><UiSelect v-model="pay.method"><option value="cash">{{ tt('نقدي', 'Cash') }}</option><option value="card">{{ tt('بطاقة', 'Card') }}</option><option value="transfer">{{ tt('تحويل', 'Transfer') }}</option></UiSelect></div>
            </div>
            <div class="row"><button class="btn ok" :disabled="busy" @click="deliver"><i class="ti ti-checks" /> {{ tt('تأكيد التسليم', 'Confirm handover') }}</button><button class="btn ghost" @click="panel = ''">{{ tt('إلغاء', 'Cancel') }}</button></div>
          </template>
          <template v-if="panel === 'deposit'">
            <div class="grid grid-3">
              <div class="field"><label>{{ tt('المبلغ', 'Amount') }} <span style="color:var(--primary)">*</span></label><input v-model.number="pay.amount" type="number" min="0" step="0.001" dir="ltr" /><small class="muted">{{ tt('المتبقي', 'Balance') }} {{ f.money(remaining) }}</small></div>
              <div class="field"><label>{{ tt('الطريقة', 'Method') }}</label><UiSelect v-model="pay.method"><option value="cash">{{ tt('نقدي', 'Cash') }}</option><option value="card">{{ tt('بطاقة', 'Card') }}</option><option value="transfer">{{ tt('تحويل', 'Transfer') }}</option></UiSelect></div>
              <div class="field"><label>{{ tt('ملاحظة', 'Note') }}</label><input v-model="pay.note" /></div>
            </div>
            <div class="row"><button class="btn" :disabled="busy || !(+pay.amount! > 0)" @click="deposit"><i class="ti ti-check" /> {{ tt('حفظ', 'Save') }}</button><button class="btn ghost" @click="panel = ''">{{ tt('إلغاء', 'Cancel') }}</button></div>
          </template>
          <template v-if="panel === 'cancel'">
            <div class="grid grid-3">
              <div class="field" style="grid-column:span 2"><label>{{ tt('سبب الإلغاء', 'Reason') }}</label><input v-model="cancel.reason" /></div>
              <div v-if="+o.paid > 0" class="field"><label>{{ tt('العربون المستلم', 'Deposit held') }}: {{ f.money(o.paid) }}</label>
                <UiSelect v-model="cancel.refund"><option :value="true">{{ tt('يُرد للعميل', 'Refund to customer') }}</option><option :value="false">{{ tt('لا يُرد (يُسجّل إيراداً)', 'Keep it (recorded as income)') }}</option></UiSelect></div>
            </div>
            <div class="row"><button class="btn" style="background:var(--primary)" :disabled="busy" @click="doCancel"><i class="ti ti-x" /> {{ tt('تأكيد الإلغاء', 'Confirm cancel') }}</button><button class="btn ghost" @click="panel = ''">{{ tt('رجوع', 'Back') }}</button></div>
          </template>
        </div>
      </div>

      <div class="grid grid-2 mt" style="align-items:start">
        <div class="card">
          <div class="card-title"><i class="ti ti-list-details" /> {{ tt('محتوى الطلب', 'Items') }}</div>
          <table class="table">
            <thead><tr><th>{{ tt('الصنف', 'Item') }}</th><th>{{ tt('الكمية', 'Qty') }}</th><th>{{ tt('السعر', 'Price') }}</th><th>{{ tt('الإجمالي', 'Total') }}</th></tr></thead>
            <tbody><tr v-for="l in o.lines" :key="l.id"><td>{{ l.description }}</td><td class="num">{{ f.qty(l.qty) }} {{ l.unit }}</td><td class="num">{{ f.money(l.unit_price) }}</td><td class="num">{{ f.money(l.total) }}</td></tr></tbody>
          </table>
          <p v-if="o.notes" class="alert warn" style="margin-top:10px;white-space:pre-line"><i class="ti ti-note" /> {{ o.notes }}</p>
        </div>
        <div>
          <div class="card">
            <div class="card-title"><i :class="`ti ${o.fulfilment === 'delivery' ? 'ti-truck-delivery' : 'ti-building-store'}`" /> {{ o.fulfilment === 'delivery' ? tt('توصيل', 'Delivery') : tt('استلام من المحل', 'Pickup') }}</div>
            <p v-if="o.fulfilment === 'delivery'" style="margin:0;line-height:1.9">{{ o.address }}<br /><span class="muted">{{ tt('السائق', 'Driver') }}: {{ o.driver || '—' }} · {{ tt('رسوم', 'fee') }} {{ f.money(o.delivery_fee) }}</span></p>
            <p v-else class="muted" style="margin:0">{{ tt('يستلمه العميل من المحل في الموعد.', 'The customer picks it up at the due time.') }}</p>
          </div>
          <div class="card mt totals-box">
            <div class="row between"><span>{{ tt('المجموع', 'Subtotal') }}</span><b>{{ f.money(o.subtotal) }}</b></div>
            <div v-if="+o.discount" class="row between"><span>{{ tt('الخصم', 'Discount') }}</span><b>- {{ f.money(o.discount) }}</b></div>
            <div v-if="+o.delivery_fee" class="row between"><span>{{ tt('التوصيل', 'Delivery') }}</span><b>{{ f.money(o.delivery_fee) }}</b></div>
            <div class="row between"><span>{{ tt('الضريبة', 'Tax') }}</span><b>{{ f.money(o.tax) }}</b></div>
            <div class="row between total"><span>{{ tt('الإجمالي', 'Total') }}</span><b>{{ f.money(o.total, true) }}</b></div>
            <div v-if="o.status !== 'delivered'" class="row between" style="color:var(--ok)"><span>{{ o.status === 'cancelled' ? tt('عربون محتفظ به', 'Deposit kept') : tt('العربون المدفوع', 'Deposit paid') }}</span><b>{{ f.money(o.paid) }}</b></div>
            <div v-if="open" class="row between"><span>{{ tt('المتبقي', 'Balance') }}</span><b style="color:var(--primary)">{{ f.money(remaining) }}</b></div>
            <div v-if="o.invoice" class="row between" style="margin-top:6px"><span>{{ tt('الفاتورة', 'Invoice') }}</span><NuxtLink :to="`/crm/invoices/${o.invoice.id}`" dir="ltr" style="color:var(--info)">{{ o.invoice.number }}</NuxtLink></div>
          </div>
          <div v-if="o.payments.length" class="card mt">
            <div class="card-title"><i class="ti ti-cash" /> {{ tt('العرابين والدفعات', 'Deposits') }}</div>
            <table class="table"><tbody><tr v-for="p in o.payments" :key="p.id"><td dir="ltr" style="text-align:right">{{ p.date }}</td><td>{{ p.kind === 'refund' ? tt('رد', 'Refund') : tt('عربون', 'Deposit') }} · {{ f.methodLabel(p.method) }}</td><td class="num" :style="{ color: p.kind === 'refund' ? 'var(--primary)' : '' }">{{ p.kind === 'refund' ? '-' : '' }}{{ f.money(p.amount) }}</td><td><small class="muted">{{ p.user?.name }} {{ p.note }}</small></td></tr></tbody></table>
          </div>
        </div>
      </div>
    </div>

    <!-- Print: kitchen / preparation ticket, or the customer's receipt -->
    <div class="print-only pos-receipt">
      <template v-if="printMode === 'ticket'">
        <h2 style="margin:0;text-align:center">{{ tt('تذكرة تحضير', 'PREP TICKET') }}</h2>
        <p style="text-align:center;margin:2px 0;font-size:18px"><b dir="ltr">{{ o.number }}</b></p>
        <p style="text-align:center;margin:2px 0;font-size:16px"><b>{{ dueLabel(o.due_at) }}</b> · {{ o.fulfilment === 'delivery' ? tt('توصيل', 'DELIVERY') : tt('استلام', 'PICKUP') }}</p>
        <p style="margin:4px 0">{{ name }} <span dir="ltr">{{ phone }}</span></p>
        <hr />
        <div v-for="l in o.lines" :key="l.id" style="font-size:16px;margin:6px 0"><b>{{ f.qty(l.qty) }} ×</b> {{ l.description }}</div>
        <hr v-if="o.notes" />
        <p v-if="o.notes" style="font-size:15px;white-space:pre-line"><b>{{ tt('ملاحظات:', 'Notes:') }}</b> {{ o.notes }}</p>
        <p v-if="o.fulfilment === 'delivery'" style="font-size:13px"><b>{{ tt('العنوان:', 'Address:') }}</b> {{ o.address }}</p>
      </template>
      <template v-else>
        <h3 style="margin:0;text-align:center">{{ d.company.name }}</h3>
        <p style="text-align:center;margin:2px 0">{{ tt('إيصال طلب', 'Order receipt') }} <b dir="ltr">{{ o.number }}</b><br /><small>{{ tt('موعد التسليم', 'Due') }}: {{ dueLabel(o.due_at) }}</small></p>
        <p style="margin:2px 0">{{ name }} <span dir="ltr">{{ phone }}</span></p>
        <hr />
        <table style="width:100%"><tr v-for="l in o.lines" :key="l.id"><td>{{ l.description }}<br /><small>{{ f.qty(l.qty) }} × {{ f.money(l.unit_price) }}</small></td><td style="text-align:left">{{ f.money(l.total) }}</td></tr></table>
        <hr />
        <div v-if="+o.delivery_fee" class="row between"><span>{{ tt('التوصيل', 'Delivery') }}</span><span>{{ f.money(o.delivery_fee) }}</span></div>
        <div class="row between"><span>{{ tt('الضريبة', 'Tax') }}</span><span>{{ f.money(o.tax) }}</span></div>
        <div class="row between" style="font-weight:800;font-size:15px"><span>{{ tt('الإجمالي', 'Total') }}</span><span>{{ f.money(o.total, true) }}</span></div>
        <div class="row between"><span>{{ tt('العربون', 'Deposit') }}</span><span>{{ f.money(o.paid) }}</span></div>
        <div class="row between" style="font-weight:700"><span>{{ tt('المتبقي', 'Balance') }}</span><span>{{ f.money(remaining) }}</span></div>
        <p style="text-align:center;font-size:11px;margin-top:8px">{{ d.company.phone }} — {{ tt('شكراً لكم', 'Thank you') }}</p>
      </template>
    </div>
  </div>
</template>

<style scoped>
.stepper { display: flex; gap: 4px; margin-top: 16px; }
.st { flex: 1; text-align: center; position: relative; color: var(--muted); }
.st::before { content: ''; position: absolute; top: 16px; inset-inline-start: -50%; width: 100%; height: 3px; background: var(--line); z-index: 0; }
.st:first-child::before { display: none; }
.st.done::before { background: var(--ok); }
.st .dot { position: relative; z-index: 1; width: 34px; height: 34px; border-radius: 50%; display: inline-grid; place-items: center; background: var(--bg); border: 2px solid var(--line); font-size: 16px; }
.st.done .dot { background: var(--ok); border-color: var(--ok); color: #fff; }
.st.cur .dot { box-shadow: 0 0 0 4px rgba(22, 163, 74, .2); }
.st small { display: block; margin-top: 4px; font-size: 11px; }
.st.done small { color: var(--ink); font-weight: 600; }
.panel { margin-top: 14px; border-top: 1px solid var(--line); padding-top: 14px; }
</style>
