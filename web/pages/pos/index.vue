<script setup lang="ts">
// POS screen: item grid + cart + checkout. Keyboard: Enter in search = barcode scan.
definePageMeta({ title: 'نقطة البيع', titleEn: 'Point of Sale' })
const api = useApi(); const auth = useAuth(); const f = useFmt(); const { tt } = useI18n()
const meta = ref<any>({ categories: [], warehouses: [] })
const items = ref<any[]>([]); const q = ref(''); const cat = ref('')
const cart = ref<any[]>([])
const customers = ref<any[]>([]); const customerId = ref('')
const discount = ref(0); const method = ref<'cash' | 'card' | 'transfer' | 'credit'>('cash'); const paid = ref<number | null>(null)
const shift = ref<any>(null); const openCash = ref(0)
const last = ref<any>(null); const err = ref(''); const busy = ref(false)
const taxRate = computed(() => Number(auth.state.value.tenant?.tax_rate ?? 0))
const warehouseId = computed(() => shift.value?.warehouse_id || meta.value.warehouses.find((w: any) => w.is_default)?.id || '')

// ---- Offline: the full catalog lives on the device (refreshed while online) ----
const off = useOffline()
const pack = ref<any>(null)                       // { items, customers, categories, shift, tax_rate, generated_at }
const localMode = computed(() => !off.state.online)
const packKey = () => `pos-pack:${warehouseId.value || 'default'}`
async function refreshPack() {
  try { const p = await api.get('/app/pos/offline-pack', { warehouse_id: warehouseId.value }); pack.value = p; await localKV.set(packKey(), p) } catch { /* keep the stored copy */ }
}
const norm = (v: any) => String(v ?? '').trim().toLowerCase()
function searchPack(term: string, category = '') {
  const t = norm(term)
  return (pack.value?.items || []).filter((i: any) => (!category || String(i.category_id) === String(category)) && (!t || norm(i.name).includes(t) || norm(i.sku).includes(t) || norm(i.barcode).includes(t))).slice(0, 60)
}

async function loadItems() {
  if (localMode.value && pack.value) { items.value = searchPack(q.value, cat.value); return }
  try { items.value = (await api.get('/app/inventory/items/lookup', { q: q.value, category_id: cat.value, warehouse_id: warehouseId.value })).items }
  catch (e: any) { if (e?.offline && pack.value) items.value = searchPack(q.value, cat.value); else throw e }
}
/** Enter in the search box = barcode scan: exact barcode match adds the item. */
async function onEnter() {
  const code = q.value.trim(); if (!code) return
  const local = () => (pack.value?.items || []).find((i: any) => norm(i.barcode) === norm(code))
  let hit: any = null; let list: any[] | null = null
  if (localMode.value) hit = local()
  else {
    try { const r = await api.get('/app/inventory/items/lookup', { q: code, warehouse_id: warehouseId.value }); if (r.exact) hit = r.items[0]; else list = r.items }
    catch (e: any) { if (e?.offline) hit = local(); else throw e }
  }
  if (hit) { add(hit); q.value = ''; await loadItems() }
  else if (list) items.value = list
  else { items.value = searchPack(code); if (!items.value.length) uiAlert(tt(`الباركود ${code} غير مسجّل لأي صنف.`, `Barcode ${code} is not on any item.`)) }
}
onMounted(async () => {
  pack.value = (await localKV.get(packKey())) || null
  try {
    meta.value = await api.get('/app/inventory/items/meta')
    const cur = await api.get('/app/pos/shifts/current'); shift.value = cur?.id ? cur : null
    customers.value = await api.get('/app/customers', { all: 1, active_only: 1 })
    await refreshPack()
  } catch (e: any) {
    if (!e?.offline) throw e
    // Offline from the start: everything comes from the stored pack.
    pack.value = pack.value || (await localKV.get('pos-pack:default'))
    if (pack.value) { customers.value = pack.value.customers; meta.value = { categories: pack.value.categories, warehouses: [] }; shift.value = pack.value.shift }
  }
  await loadItems()
})
// Every 5 minutes while online, and after queued sales upload, the stored catalog is refreshed.
let packTimer: any = null
onMounted(() => { packTimer = setInterval(() => { if (off.state.online) refreshPack() }, 300000) })
onBeforeUnmount(() => clearInterval(packTimer))
off.onSynced(op => { if (op.kind === 'pos_sale' && off.state.online) refreshPack() })
function add(i: any) { const ex = cart.value.find(l => l.item_id === i.id); ex ? ex.qty++ : cart.value.push({ item_id: i.id, name: i.name, qty: 1, unit_price: Number(i.sale_price), discount: 0, stock: i.stock, track: i.track_stock }) }
const subtotal = computed(() => cart.value.reduce((s, l) => s + l.qty * l.unit_price - l.discount, 0))
const taxable = computed(() => Math.max(0, subtotal.value - Number(discount.value || 0)))
const tax = computed(() => +(taxable.value * taxRate.value / 100).toFixed(3))
const total = computed(() => +(taxable.value + tax.value).toFixed(3))
const change = computed(() => paid.value !== null && paid.value > total.value ? +(paid.value - total.value).toFixed(3) : 0)
function clear() { cart.value = []; discount.value = 0; paid.value = null; method.value = 'cash'; customerId.value = ''; err.value = '' }

async function checkout() {
  err.value = ''
  if (!cart.value.length) return
  if (method.value === 'credit' && !customerId.value) { err.value = tt('البيع الآجل يتطلب اختيار عميل','Credit sale requires selecting a customer'); return }
  busy.value = true
  // Every sale carries a device number: online it guards against double-submits,
  // offline it is the receipt number until the server assigns the final one.
  const ref_ = tempNumber('OFF')
  const body = {
    customer_id: customerId.value || null, warehouse_id: warehouseId.value || null, discount: discount.value, payment_method: method.value,
    paid: method.value === 'credit' ? (paid.value || 0) : total.value, client_ref: ref_,
    lines: cart.value.map(l => ({ item_id: l.item_id, qty: l.qty, unit_price: l.unit_price, discount: l.discount })),
  }
  const receipt = { lines: cart.value.map(l => ({ ...l })), subtotal: subtotal.value, discount: +discount.value || 0, tax: tax.value, total: total.value, method: method.value, customer: customers.value.find((c: any) => String(c.id) === String(customerId.value))?.name }
  try {
    const sale = localMode.value ? null : await api.post('/app/pos/sales', body)
    if (sale) { last.value = { ...sale, change: change.value }; clear(); await loadItems(); return }
    throw new ApiError(0, 'offline', {}, true)
  } catch (e: any) {
    if (!e?.offline) { err.value = e.message; return }
    // No connection: keep selling. Queue the sale, take the stock off locally, print a local receipt.
    await off.outbox.add({ id: ref_, kind: 'pos_sale', method: 'POST', path: '/app/pos/sales', body: { ...body, offline: true, sold_at: new Date().toISOString() }, label: tt(`فاتورة بيع ${ref_} — ${f.money(total.value)}`, `Sale ${ref_} — ${f.money(total.value)}`) })
    for (const l of cart.value) { const it = pack.value?.items.find((i: any) => i.id === l.item_id); if (it && it.track_stock) it.stock = (+it.stock || 0) - l.qty }
    if (pack.value) await localKV.set(packKey(), pack.value)
    last.value = { number: ref_, total: total.value, change: change.value, offline: true, receipt, at: new Date() }
    clear(); await loadItems()
  } finally { busy.value = false }
}
async function openShift() { try { shift.value = await api.post('/app/pos/shifts/open', { opening_cash: openCash.value, warehouse_id: warehouseId.value || null }) } catch (e: any) { uiAlert(e.message) } }
function printLast() {
  // Offline sale → print the local receipt on this page; otherwise the server copy.
  if (last.value.offline) { nextTick(() => window.print()); return }
  window.open(`/app/pos/sales?print=${last.value.id}`, '_blank')
}
</script>

<template>
  <div class="pos-wrap">
  <div v-if="localMode || off.state.pending" class="offline-bar no-print" :class="{ warn: localMode }">
    <i :class="`ti ${localMode ? 'ti-wifi-off' : 'ti-cloud-upload'}`" />
    <span v-if="localMode">{{ tt('لا يوجد اتصال — البيع مستمر من بيانات الجهاز', 'Offline — selling continues from this device') }}<template v-if="pack"> · {{ tt('الأصناف محدّثة', 'catalog from') }} {{ f.dt(pack.generated_at) }}</template></span>
    <span v-else>{{ tt('جارٍ رفع المبيعات المحفوظة على الجهاز…', 'Uploading sales saved on this device…') }}</span>
    <b v-if="off.state.pending">{{ off.state.pending }} {{ tt('فاتورة بانتظار الرفع', 'waiting to upload') }}</b>
    <NuxtLink to="/offline" class="btn ghost sm">{{ tt('التفاصيل', 'Details') }}</NuxtLink>
  </div>
  <div class="pos no-print">
    <div class="pos-left card">
      <div class="row" style="margin-bottom:10px">
        <input v-model="q" :placeholder="tt('ابحث أو امسح الباركود ثم Enter','Search or scan barcode then Enter')" autofocus style="flex:1;padding:10px 14px;border:1px solid var(--line);border-radius:10px;font-size:15px" @keyup.enter="onEnter" @input="loadItems" />
        <select v-model="cat" style="padding:8px 12px;border:1px solid var(--line);border-radius:10px" @change="loadItems"><option value="">{{ tt('كل التصنيفات','All categories') }}</option><option v-for="c in meta.categories" :key="c.id" :value="c.id">{{ c.name }}</option></select>
      </div>
      <div class="pos-grid">
        <div v-for="i in items" :key="i.id" class="pos-item" :class="{ out: i.track_stock && Number(i.stock || 0) <= 0 }" @click="add(i)">
          <b>{{ i.name }}</b>
          <span class="price">{{ f.money(i.sale_price) }}</span>
          <small class="muted">{{ i.track_stock ? `${tt('المتاح','Available')}: ${f.qty(i.stock)}` : tt('خدمة','Service') }}</small>
        </div>
      </div>
      <p v-if="!items.length" class="empty">{{ tt('لا توجد أصناف مطابقة.','No matching items.') }}</p>
    </div>

    <div class="pos-right card">
      <div v-if="!shift" class="alert warn" style="background:#fef3c7;color:#92400e">
        {{ tt('لا توجد وردية مفتوحة.','No open shift.') }} <div class="row mt"><input v-model.number="openCash" type="number" step="0.001" :placeholder="tt('النقد الافتتاحي','Opening cash')" dir="ltr" style="padding:6px 10px;border:1px solid var(--line);border-radius:8px;width:140px" /><button class="btn sm" @click="openShift">{{ tt('فتح وردية','Open shift') }}</button></div>
      </div>
      <div v-else class="row between" style="margin-bottom:8px"><small class="muted">{{ tt('وردية','Shift') }} #{{ shift.id }} · {{ shift.warehouse?.name }}</small><small class="muted">{{ tt('مبيعات الوردية:','Shift sales:') }} <b>{{ f.money(shift.sales_total) }}</b></small></div>

      <div class="field" style="margin-bottom:8px"><select v-model="customerId"><option value="">{{ tt('عميل نقدي','Walk-in customer') }}</option><option v-for="c in customers" :key="c.id" :value="c.id">{{ c.name }} {{ c.phone ? `— ${c.phone}` : '' }}</option></select></div>

      <div class="cart">
        <div v-for="(l, i) in cart" :key="l.item_id" class="cart-line">
          <div style="flex:1"><b>{{ l.name }}</b><br /><small class="muted">{{ f.money(l.unit_price) }} × </small><input v-model.number="l.qty" type="number" min="0.001" step="1" dir="ltr" style="width:60px;padding:3px 6px;border:1px solid var(--line);border-radius:6px" /></div>
          <div class="num" style="min-width:80px;text-align:left"><b>{{ f.money(l.qty * l.unit_price - l.discount) }}</b></div>
          <button class="btn ghost sm icon" style="color:var(--primary)" @click="cart.splice(i, 1)"><i class="ti ti-x" /></button>
        </div>
        <p v-if="!cart.length" class="empty">{{ tt('السلة فارغة','Cart is empty') }}</p>
      </div>

      <div class="totals">
        <div class="row between"><span>{{ tt('المجموع','Subtotal') }}</span><b>{{ f.money(subtotal) }}</b></div>
        <div class="row between"><span>{{ tt('خصم','Discount') }}</span><input v-model.number="discount" type="number" step="0.001" dir="ltr" style="width:100px;padding:4px 8px;border:1px solid var(--line);border-radius:6px" /></div>
        <div class="row between"><span>{{ tt('ضريبة','Tax') }} {{ taxRate }}%</span><b>{{ f.money(tax) }}</b></div>
        <div class="row between total"><span>{{ tt('الإجمالي','Total') }}</span><b>{{ f.money(total, true) }}</b></div>
        <div class="chips" style="margin:8px 0"><span v-for="m in ['cash','card','transfer','credit']" :key="m" class="chip" :class="{ on: method === m }" @click="method = m as any">{{ f.methodLabel(m) }}</span></div>
        <div class="row between"><span>{{ method === 'credit' ? tt('دفعة مقدمة','Down payment') : tt('المدفوع','Paid') }}</span><input v-model.number="paid" type="number" step="0.001" dir="ltr" :placeholder="f.money(total)" style="width:120px;padding:4px 8px;border:1px solid var(--line);border-radius:6px" /></div>
        <div v-if="change > 0" class="row between" style="color:var(--ok)"><span>{{ tt('الباقي للعميل','Change due') }}</span><b>{{ f.money(change) }}</b></div>
      </div>
      <div v-if="err" class="alert bad">{{ err }}</div>
      <div class="row" style="margin-top:8px">
        <button class="btn ghost" @click="clear">{{ tt('إفراغ','Clear') }}</button>
        <button class="btn ok" style="flex:1;justify-content:center;font-size:16px;padding:12px" :disabled="!cart.length || busy" @click="checkout"><i class="ti ti-check" /> {{ tt('إتمام البيع','Complete sale') }}</button>
      </div>
      <div v-if="last" class="alert mt row between" :class="last.offline ? 'warn' : 'ok'"><span>{{ tt('تمت الفاتورة','Invoice done') }} <b dir="ltr">{{ last.number }}</b> — {{ f.money(last.total, true) }}<span v-if="last.change"> · {{ tt('الباقي','Change') }} {{ f.money(last.change) }}</span><small v-if="last.offline" style="display:block">{{ tt('رقم مؤقت — تُرفع وتأخذ رقمها النهائي عند عودة الإنترنت', 'Temporary number — uploads and gets its final number when back online') }}</small></span><button class="btn ghost sm" @click="printLast"><i class="ti ti-printer" /> {{ tt('طباعة','Print') }}</button></div>
    </div>
  </div>

  <!-- Local receipt for an offline sale (printed from this page). -->
  <div v-if="last?.offline" class="print-only pos-receipt">
    <h3 style="margin:0;text-align:center">{{ auth.state.value.tenant?.name }}</h3>
    <p style="text-align:center;margin:2px 0">{{ tt('فاتورة بيع', 'Sales receipt') }} <b dir="ltr">{{ last.number }}</b><br /><small>{{ f.dt(last.at.toISOString()) }} · {{ tt('رقم مؤقت', 'temporary no.') }}</small></p>
    <p v-if="last.receipt.customer" style="margin:2px 0">{{ tt('العميل', 'Customer') }}: {{ last.receipt.customer }}</p>
    <hr />
    <table style="width:100%"><tr v-for="l in last.receipt.lines" :key="l.item_id"><td>{{ l.name }}<br /><small>{{ f.qty(l.qty) }} × {{ f.money(l.unit_price) }}</small></td><td style="text-align:left">{{ f.money(l.qty * l.unit_price - l.discount) }}</td></tr></table>
    <hr />
    <div class="row between"><span>{{ tt('المجموع', 'Subtotal') }}</span><span>{{ f.money(last.receipt.subtotal) }}</span></div>
    <div v-if="last.receipt.discount" class="row between"><span>{{ tt('خصم', 'Discount') }}</span><span>- {{ f.money(last.receipt.discount) }}</span></div>
    <div class="row between"><span>{{ tt('الضريبة', 'Tax') }}</span><span>{{ f.money(last.receipt.tax) }}</span></div>
    <div class="row between" style="font-weight:800;font-size:15px"><span>{{ tt('الإجمالي', 'Total') }}</span><span>{{ f.money(last.receipt.total, true) }}</span></div>
    <p style="text-align:center;font-size:11px;margin-top:8px">{{ tt('شكراً لزيارتكم', 'Thank you') }}</p>
  </div>
  </div>
</template>

<style scoped>
.offline-bar { display: flex; align-items: center; gap: 10px; flex-wrap: wrap; padding: 10px 14px; border-radius: 12px; margin-bottom: 12px; background: #e0f2fe; color: #075985; }
.offline-bar.warn { background: #fef3c7; color: #92400e; }
.offline-bar .btn { margin-inline-start: auto; }
.print-only { display: none; }
@media print { .print-only { display: block; } .pos-receipt { max-width: 80mm; margin: 0 auto; font-size: 12px; } .pos-receipt hr { border: 0; border-top: 1px dashed #999; } }
.pos { display: grid; grid-template-columns: 1fr 380px; gap: 16px; min-height: calc(100vh - 160px); }
@media (max-width: 900px) { .pos { grid-template-columns: 1fr; } }
.pos-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(150px, 1fr)); gap: 10px; }
.pos-item { border: 1px solid var(--line); border-radius: 12px; padding: 12px; cursor: pointer; display: flex; flex-direction: column; gap: 4px; min-height: 90px; }
.pos-item:hover { border-color: var(--primary); background: var(--primary-50); }
.pos-item.out { opacity: .5; }
.pos-item .price { color: var(--primary); font-weight: 800; }
.pos-right { display: flex; flex-direction: column; }
.cart { flex: 1; overflow-y: auto; max-height: 40vh; border-top: 1px solid var(--line); border-bottom: 1px solid var(--line); padding: 6px 0; }
.cart-line { display: flex; align-items: center; gap: 8px; padding: 8px 0; border-bottom: 1px dashed var(--line); }
.totals { padding-top: 10px; display: flex; flex-direction: column; gap: 6px; }
.totals .total { font-size: 18px; color: var(--primary); border-top: 1px solid var(--line); padding-top: 6px; }
</style>
