<script setup lang="ts">
// POS screen: item grid + cart + checkout. Keyboard: Enter in search = barcode scan.
definePageMeta({ title: 'نقطة البيع', titleEn: 'Point of Sale' })
const api = useApi(); const auth = useAuth(); const f = useFmt()
const meta = ref<any>({ categories: [], warehouses: [] })
const items = ref<any[]>([]); const q = ref(''); const cat = ref('')
const cart = ref<any[]>([])
const customers = ref<any[]>([]); const customerId = ref('')
const discount = ref(0); const method = ref<'cash' | 'card' | 'transfer' | 'credit'>('cash'); const paid = ref<number | null>(null)
const shift = ref<any>(null); const openCash = ref(0)
const last = ref<any>(null); const err = ref(''); const busy = ref(false)
const taxRate = computed(() => Number(auth.state.value.tenant?.tax_rate ?? 0))
const warehouseId = computed(() => shift.value?.warehouse_id || meta.value.warehouses.find((w: any) => w.is_default)?.id || '')

async function loadItems() { items.value = (await api.get('/app/inventory/items/lookup', { q: q.value, category_id: cat.value, warehouse_id: warehouseId.value })).items }
async function onEnter() {
  const r = await api.get('/app/inventory/items/lookup', { q: q.value, warehouse_id: warehouseId.value })
  if (r.exact && r.items[0]) { add(r.items[0]); q.value = ''; await loadItems() } else items.value = r.items
}
onMounted(async () => {
  meta.value = await api.get('/app/inventory/items/meta')
  shift.value = await api.get('/app/pos/shifts/current')
  customers.value = await api.get('/app/customers', { all: 1, active_only: 1 })
  await loadItems()
})
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
  if (method.value === 'credit' && !customerId.value) { err.value = 'البيع الآجل يتطلب اختيار عميل'; return }
  busy.value = true
  try {
    const sale = await api.post('/app/pos/sales', {
      customer_id: customerId.value || null, warehouse_id: warehouseId.value || null, discount: discount.value, payment_method: method.value,
      paid: method.value === 'credit' ? (paid.value || 0) : total.value,
      lines: cart.value.map(l => ({ item_id: l.item_id, qty: l.qty, unit_price: l.unit_price, discount: l.discount })),
    })
    last.value = { ...sale, change: change.value }
    clear(); await loadItems()
  } catch (e: any) { err.value = e.message } finally { busy.value = false }
}
async function openShift() { try { shift.value = await api.post('/app/pos/shifts/open', { opening_cash: openCash.value, warehouse_id: warehouseId.value || null }) } catch (e: any) { alert(e.message) } }
function printLast() { window.open(`/app/pos/sales?print=${last.value.id}`, '_blank') }
</script>

<template>
  <div class="pos">
    <div class="pos-left card">
      <div class="row" style="margin-bottom:10px">
        <input v-model="q" placeholder="ابحث أو امسح الباركود ثم Enter" autofocus style="flex:1;padding:10px 14px;border:1px solid var(--line);border-radius:10px;font-size:15px" @keyup.enter="onEnter" @input="loadItems" />
        <select v-model="cat" style="padding:8px 12px;border:1px solid var(--line);border-radius:10px" @change="loadItems"><option value="">كل التصنيفات</option><option v-for="c in meta.categories" :key="c.id" :value="c.id">{{ c.name }}</option></select>
      </div>
      <div class="pos-grid">
        <div v-for="i in items" :key="i.id" class="pos-item" :class="{ out: i.track_stock && Number(i.stock || 0) <= 0 }" @click="add(i)">
          <b>{{ i.name }}</b>
          <span class="price">{{ f.money(i.sale_price) }}</span>
          <small class="muted">{{ i.track_stock ? `المتاح: ${f.qty(i.stock)}` : 'خدمة' }}</small>
        </div>
      </div>
      <p v-if="!items.length" class="empty">لا توجد أصناف مطابقة.</p>
    </div>

    <div class="pos-right card">
      <div v-if="!shift" class="alert warn" style="background:#fef3c7;color:#92400e">
        لا توجد وردية مفتوحة. <div class="row mt"><input v-model.number="openCash" type="number" step="0.001" placeholder="النقد الافتتاحي" dir="ltr" style="padding:6px 10px;border:1px solid var(--line);border-radius:8px;width:140px" /><button class="btn sm" @click="openShift">فتح وردية</button></div>
      </div>
      <div v-else class="row between" style="margin-bottom:8px"><small class="muted">وردية #{{ shift.id }} · {{ shift.warehouse?.name }}</small><small class="muted">مبيعات الوردية: <b>{{ f.money(shift.sales_total) }}</b></small></div>

      <div class="field" style="margin-bottom:8px"><select v-model="customerId"><option value="">عميل نقدي</option><option v-for="c in customers" :key="c.id" :value="c.id">{{ c.name }} {{ c.phone ? `— ${c.phone}` : '' }}</option></select></div>

      <div class="cart">
        <div v-for="(l, i) in cart" :key="l.item_id" class="cart-line">
          <div style="flex:1"><b>{{ l.name }}</b><br /><small class="muted">{{ f.money(l.unit_price) }} × </small><input v-model.number="l.qty" type="number" min="0.001" step="1" dir="ltr" style="width:60px;padding:3px 6px;border:1px solid var(--line);border-radius:6px" /></div>
          <div class="num" style="min-width:80px;text-align:left"><b>{{ f.money(l.qty * l.unit_price - l.discount) }}</b></div>
          <button class="btn ghost sm icon" style="color:var(--primary)" @click="cart.splice(i, 1)"><i class="ti ti-x" /></button>
        </div>
        <p v-if="!cart.length" class="empty">السلة فارغة</p>
      </div>

      <div class="totals">
        <div class="row between"><span>المجموع</span><b>{{ f.money(subtotal) }}</b></div>
        <div class="row between"><span>خصم</span><input v-model.number="discount" type="number" step="0.001" dir="ltr" style="width:100px;padding:4px 8px;border:1px solid var(--line);border-radius:6px" /></div>
        <div class="row between"><span>ضريبة {{ taxRate }}%</span><b>{{ f.money(tax) }}</b></div>
        <div class="row between total"><span>الإجمالي</span><b>{{ f.money(total, true) }}</b></div>
        <div class="chips" style="margin:8px 0"><span v-for="m in ['cash','card','transfer','credit']" :key="m" class="chip" :class="{ on: method === m }" @click="method = m as any">{{ f.methodLabel(m) }}</span></div>
        <div class="row between"><span>{{ method === 'credit' ? 'دفعة مقدمة' : 'المدفوع' }}</span><input v-model.number="paid" type="number" step="0.001" dir="ltr" :placeholder="f.money(total)" style="width:120px;padding:4px 8px;border:1px solid var(--line);border-radius:6px" /></div>
        <div v-if="change > 0" class="row between" style="color:var(--ok)"><span>الباقي للعميل</span><b>{{ f.money(change) }}</b></div>
      </div>
      <div v-if="err" class="alert bad">{{ err }}</div>
      <div class="row" style="margin-top:8px">
        <button class="btn ghost" @click="clear">إفراغ</button>
        <button class="btn ok" style="flex:1;justify-content:center;font-size:16px;padding:12px" :disabled="!cart.length || busy" @click="checkout"><i class="ti ti-check" /> إتمام البيع</button>
      </div>
      <div v-if="last" class="alert ok mt row between"><span>تمت الفاتورة <b>{{ last.number }}</b> — {{ f.money(last.total, true) }}<span v-if="last.change"> · الباقي {{ f.money(last.change) }}</span></span><button class="btn ghost sm" @click="printLast"><i class="ti ti-printer" /> طباعة</button></div>
    </div>
  </div>
</template>

<style scoped>
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
