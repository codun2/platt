<script setup lang="ts">
/**
 * Public order page (add-on «صفحة الطلب أونلاين»). No account: the customer picks
 * items, chooses pickup / delivery and a time, and the order reaches the shop's
 * pre-orders board. Prices shown here are only a preview — the server prices it.
 */
definePageMeta({ layout: 'blank', title: 'اطلب أونلاين', titleEn: 'Order online' })
const route = useRoute(); const config = useRuntimeConfig()
const handle = String(route.params.handle)
const d = ref<any>(null); const failed = ref(''); const cat = ref<number | ''>(''); const q = ref('')
const cart = ref<Record<number, { qty: number; note: string }>>({})
const step = ref<'menu' | 'checkout' | 'done'>('menu')
const form = reactive({ name: '', phone: '', fulfilment: 'pickup', address: '', due_at: '', notes: '' })
const err = ref(''); const sending = ref(false); const done = ref<any>(null)
const KEY = `rakeez_store_${handle}`

const money = (n: number) => `${(+n || 0).toFixed(3).replace(/\.?0+$/, '') || '0'} ${d.value?.store.currency === 'SAR' || !d.value?.store.currency ? tt('ريال', 'SAR') : d.value.store.currency}`
const api = (p: string, init?: RequestInit) => fetch(`${config.public.apiBase}/public/store/${encodeURIComponent(handle)}${p}`, { ...init, headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-Locale': currentLocale() } })
const localIso = (dt: Date) => new Date(dt.getTime() - dt.getTimezoneOffset() * 60000).toISOString().slice(0, 16)
const earliest = computed(() => d.value ? localIso(new Date(Date.now() + (+d.value.store.lead_hours || 0) * 3600000)) : '')

onMounted(async () => {
  try {
    const r = await api(''); if (!r.ok) throw new Error(String(r.status))
    d.value = await r.json()
    document.title = d.value.store.name
    form.fulfilment = d.value.store.pickup ? 'pickup' : 'delivery'
    // Returning visitor: name / phone / address remembered on this device only.
    try { Object.assign(form, JSON.parse(localStorage.getItem(KEY) || '{}')) } catch { /* private mode */ }
    if (!d.value.store.pickup) form.fulfilment = 'delivery'
    if (!d.value.store.delivery) form.fulfilment = 'pickup'
  } catch (e: any) { failed.value = e.message === '404' ? tt('هذا المتجر غير موجود أو أُغلق.', 'This store does not exist or is closed.') : tt('تعذّر فتح المتجر، تحقق من الاتصال وحاول مرة أخرى.', 'Could not open the store, check your connection and retry.') }
})
const accent = computed(() => d.value?.store.accent || '#e11d48')
const shown = computed(() => (d.value?.items || []).filter((i: any) => (!cat.value || i.category_id === cat.value) && (!q.value || i.name.includes(q.value.trim()))))
const lines = computed(() => Object.entries(cart.value).map(([id, c]) => ({ item: d.value.items.find((i: any) => i.id === +id), ...c })).filter(l => l.item && l.qty > 0))
const count = computed(() => lines.value.reduce((n, l) => n + l.qty, 0))
const subtotal = computed(() => lines.value.reduce((n, l) => n + l.qty * l.item.price, 0))
const fee = computed(() => form.fulfilment === 'delivery' ? +d.value?.store.delivery_fee || 0 : 0)
const tax = computed(() => (subtotal.value + fee.value) * (+d.value?.store.tax_rate || 0) / 100)
const total = computed(() => subtotal.value + fee.value + tax.value)
const belowMin = computed(() => +d.value?.store.min_order > 0 && subtotal.value < +d.value.store.min_order)
function add(i: any, n = 1) {
  const c = cart.value[i.id] || { qty: 0, note: '' }
  c.qty = Math.max(0, c.qty + n)
  if (i.left !== null && c.qty > i.left) c.qty = i.left
  if (c.qty === 0) delete cart.value[i.id]; else cart.value[i.id] = c
}
async function send() {
  err.value = ''
  if (!form.name.trim() || !form.phone.trim()) { err.value = tt('اكتب الاسم ورقم الجوال.', 'Enter your name and phone.'); return }
  if (form.fulfilment === 'delivery' && !form.address.trim()) { err.value = tt('اكتب عنوان التوصيل.', 'Enter the delivery address.'); return }
  sending.value = true
  try {
    const r = await api('/order', { method: 'POST', body: JSON.stringify({ ...form, due_at: form.due_at || null, tz: new Date().getTimezoneOffset(), lines: lines.value.map(l => ({ item_id: l.item.id, qty: l.qty, note: l.note || null })) }) })
    const j = await r.json().catch(() => ({}))
    if (!r.ok) { err.value = (Object.values(j.errors || {})[0] as any)?.[0] || j.message || tt('تعذّر إرسال الطلب.', 'Could not send the order.'); return }
    done.value = j; step.value = 'done'; cart.value = {}
    try { localStorage.setItem(KEY, JSON.stringify({ name: form.name, phone: form.phone, address: form.address })) } catch { /* private mode */ }
    window.scrollTo({ top: 0 })
  } catch { err.value = tt('لا يوجد اتصال بالإنترنت، حاول مرة أخرى.', 'No internet connection, try again.') } finally { sending.value = false }
}
const waShop = computed(() => {
  const p = (d.value?.store.whatsapp || '').replace(/\D/g, ''); if (!p) return ''
  const n = p.startsWith('00') ? p.slice(2) : p.startsWith('0') ? '966' + p.slice(1) : p.length <= 9 ? '966' + p : p
  const text = done.value ? tt(`مرحباً، أرسلت طلباً رقم ${done.value.number} باسم ${form.name}.`, `Hi, I placed order ${done.value.number} (${form.name}).`) : tt('مرحباً، عندي استفسار عن الطلب.', 'Hi, I have a question.')
  return `https://wa.me/${n}?text=${encodeURIComponent(text)}`
})
</script>

<template>
  <div class="store" :style="{ '--sa': accent }">
    <div v-if="failed" class="center"><i class="ti ti-building-store" style="font-size:48px;color:#94a3b8" /><p>{{ failed }}</p></div>
    <div v-else-if="!d" class="center"><i class="ti ti-loader-2 spin" style="font-size:32px" /></div>
    <template v-else>
      <header class="hero">
        <h1>{{ d.store.name }}</h1>
        <p v-if="d.store.welcome">{{ d.store.welcome }}</p>
        <div class="meta">
          <span v-if="d.store.pickup"><i class="ti ti-building-store" /> {{ tt('استلام من المحل', 'Pickup') }}</span>
          <span v-if="d.store.delivery"><i class="ti ti-truck-delivery" /> {{ tt('توصيل', 'Delivery') }}<template v-if="+d.store.delivery_fee">&nbsp;{{ money(d.store.delivery_fee) }}</template></span>
          <a v-if="waShop" :href="waShop" target="_blank"><i class="ti ti-brand-whatsapp" /> {{ tt('واتساب', 'WhatsApp') }}</a>
        </div>
      </header>

      <main class="wrap">
        <div v-if="!d.store.open" class="note"><i class="ti ti-clock-pause" /> {{ tt('المتجر لا يستقبل طلبات أونلاين حالياً.', 'The store is not taking online orders right now.') }}</div>
        <div v-else-if="d.store.notice && step !== 'done'" class="note"><i class="ti ti-info-circle" /> {{ d.store.notice }}</div>

        <!-- done -->
        <div v-if="step === 'done'" class="done">
          <i class="ti ti-circle-check" />
          <h2>{{ tt('تم استلام طلبك', 'Order received') }}</h2>
          <p>{{ tt('رقم الطلب', 'Order no.') }} <b dir="ltr">{{ done.number }}</b></p>
          <p>{{ done.fulfilment === 'delivery' ? tt('موعد التوصيل', 'Delivery') : tt('موعد الاستلام', 'Pickup') }}: <b dir="ltr">{{ done.due_at }}</b> · {{ tt('الإجمالي', 'Total') }} <b>{{ money(done.total) }}</b></p>
          <p class="muted">{{ tt('سيتواصل معك المحل لتأكيد الطلب. الدفع عند الاستلام.', 'The shop will contact you to confirm. Pay on pickup / delivery.') }}</p>
          <a v-if="waShop" :href="waShop" target="_blank" class="sbtn wa"><i class="ti ti-brand-whatsapp" /> {{ tt('أرسل رقم الطلب للمحل على واتساب', 'Send the order number on WhatsApp') }}</a>
          <button class="sbtn ghost" @click="step = 'menu'">{{ tt('طلب جديد', 'New order') }}</button>
        </div>

        <!-- menu -->
        <template v-else-if="step === 'menu' && d.store.open">
          <input v-model="q" class="search" :placeholder="tt('ابحث عن صنف…', 'Search…')" />
          <div v-if="d.categories.length" class="cats">
            <button :class="{ on: !cat }" @click="cat = ''">{{ tt('الكل', 'All') }}</button>
            <button v-for="c in d.categories" :key="c.id" :class="{ on: cat === c.id }" @click="cat = c.id">{{ c.name }}</button>
          </div>
          <div class="grid">
            <div v-for="i in shown" :key="i.id" class="item" :class="{ in: cart[i.id] }">
              <div class="img" :style="i.image ? { backgroundImage: `url(${i.image})` } : {}"><i v-if="!i.image" class="ti ti-photo" /></div>
              <div class="body">
                <b>{{ i.name }}</b>
                <span class="price">{{ money(i.price) }}<small v-if="i.unit"> / {{ i.unit }}</small></span>
                <small v-if="i.left !== null && i.left <= 5" class="left">{{ tt(`متبقٍ ${i.left}`, `${i.left} left`) }}</small>
                <div v-if="cart[i.id]" class="qty"><button @click="add(i, -1)">−</button><b>{{ cart[i.id].qty }}</b><button @click="add(i, 1)">+</button></div>
                <button v-else class="sbtn sm" @click="add(i)"><i class="ti ti-plus" /> {{ tt('أضف', 'Add') }}</button>
              </div>
            </div>
          </div>
          <p v-if="!shown.length" class="center muted">{{ tt('لا توجد أصناف.', 'No items.') }}</p>
        </template>

        <!-- checkout -->
        <div v-else-if="step === 'checkout'" class="checkout">
          <button class="back" @click="step = 'menu'"><i class="ti ti-arrow-right" /> {{ tt('رجوع للأصناف', 'Back to items') }}</button>
          <h2>{{ tt('طلبك', 'Your order') }}</h2>
          <div v-for="l in lines" :key="l.item.id" class="cl">
            <div class="row"><b style="flex:1">{{ l.item.name }}</b><div class="qty"><button @click="add(l.item, -1)">−</button><b>{{ l.qty }}</b><button @click="add(l.item, 1)">+</button></div><span class="lt">{{ money(l.qty * l.item.price) }}</span></div>
            <input v-model="cart[l.item.id].note" class="inp sm" :placeholder="tt('ملاحظة على هذا الصنف (اختياري)', 'Note for this item (optional)')" />
          </div>
          <h3>{{ tt('بياناتك', 'Your details') }}</h3>
          <input v-model="form.name" class="inp" :placeholder="tt('الاسم', 'Name')" autocomplete="name" />
          <input v-model="form.phone" class="inp" dir="ltr" type="tel" :placeholder="tt('رقم الجوال', 'Mobile')" autocomplete="tel" />
          <div class="seg">
            <button v-if="d.store.pickup" :class="{ on: form.fulfilment === 'pickup' }" @click="form.fulfilment = 'pickup'"><i class="ti ti-building-store" /> {{ tt('استلام من المحل', 'Pickup') }}</button>
            <button v-if="d.store.delivery" :class="{ on: form.fulfilment === 'delivery' }" @click="form.fulfilment = 'delivery'"><i class="ti ti-truck-delivery" /> {{ tt('توصيل', 'Delivery') }}</button>
          </div>
          <textarea v-if="form.fulfilment === 'delivery'" v-model="form.address" class="inp" rows="2" :placeholder="tt('العنوان: الحي، الشارع، أقرب معلم أو رابط الموقع', 'Address: district, street, landmark or map link')" />
          <label class="lbl">{{ tt('الموعد المطلوب (اتركه فارغاً لأقرب وقت)', 'When (leave empty for the earliest)') }}</label>
          <input v-model="form.due_at" class="inp" type="datetime-local" dir="ltr" :min="earliest" />
          <textarea v-model="form.notes" class="inp" rows="2" :placeholder="tt('ملاحظات للمحل (اختياري)', 'Notes for the shop (optional)')" />
          <div class="sum">
            <div><span>{{ tt('المجموع', 'Subtotal') }}</span><b>{{ money(subtotal) }}</b></div>
            <div v-if="fee"><span>{{ tt('التوصيل', 'Delivery') }}</span><b>{{ money(fee) }}</b></div>
            <div v-if="tax"><span>{{ tt('الضريبة', 'Tax') }}</span><b>{{ money(tax) }}</b></div>
            <div class="t"><span>{{ tt('الإجمالي', 'Total') }}</span><b>{{ money(total) }}</b></div>
            <small class="muted">{{ tt('الدفع عند الاستلام أو التوصيل.', 'Pay on pickup / delivery.') }}</small>
          </div>
          <div v-if="err" class="errbox">{{ err }}</div>
          <button class="sbtn big" :disabled="sending || !lines.length || belowMin" @click="send"><i :class="`ti ${sending ? 'ti-loader-2 spin' : 'ti-send'}`" /> {{ tt('إرسال الطلب', 'Send order') }}</button>
        </div>
      </main>

      <div v-if="step === 'menu' && count" class="cartbar" @click="step = 'checkout'">
        <span><i class="ti ti-shopping-bag" /> {{ count }} {{ tt('صنف', 'items') }} · {{ money(subtotal) }}</span>
        <b v-if="belowMin">{{ tt('الحد الأدنى', 'Minimum') }} {{ money(d.store.min_order) }}</b>
        <b v-else>{{ tt('متابعة الطلب', 'Checkout') }} <i class="ti ti-chevron-left" /></b>
      </div>
      <footer class="foot">{{ tt('مدعوم من', 'Powered by') }} <b>ركائز</b></footer>
    </template>
  </div>
</template>

<style scoped>
.store { min-height: 100vh; background: #f8fafc; color: #0f172a; font-family: 'Cairo', system-ui, sans-serif; padding-bottom: 90px; }
.center { text-align: center; padding: 80px 16px; }
.hero { background: var(--sa); color: #fff; padding: 28px 16px 22px; text-align: center; }
.hero h1 { margin: 0; font-size: 24px; }
.hero p { margin: 6px 0 0; opacity: .92; }
.meta { display: flex; gap: 14px; justify-content: center; flex-wrap: wrap; margin-top: 12px; font-size: 13px; }
.meta a { color: #fff; text-decoration: none; }
.wrap { max-width: 860px; margin: 0 auto; padding: 14px 16px; }
.note { background: #fffbeb; color: #92400e; border-radius: 12px; padding: 10px 12px; margin-bottom: 12px; font-size: 14px; }
.search, .inp { width: 100%; box-sizing: border-box; padding: 12px 14px; border: 1px solid #e2e8f0; border-radius: 12px; font: inherit; font-size: 15px; background: #fff; margin-bottom: 10px; }
.inp.sm { padding: 8px 10px; font-size: 13px; margin: 6px 0 0; }
.cats { display: flex; gap: 8px; overflow-x: auto; padding-bottom: 6px; margin-bottom: 8px; }
.cats button { flex-shrink: 0; border: 1px solid #e2e8f0; background: #fff; border-radius: 999px; padding: 6px 14px; font: inherit; font-size: 13px; cursor: pointer; }
.cats button.on { background: var(--sa); border-color: var(--sa); color: #fff; }
.grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(160px, 1fr)); gap: 10px; }
.item { background: #fff; border: 1px solid #e2e8f0; border-radius: 14px; overflow: hidden; display: flex; flex-direction: column; }
.item.in { border-color: var(--sa); }
.img { height: 110px; background: #f1f5f9 center / cover; display: grid; place-items: center; color: #cbd5e1; font-size: 30px; }
.body { padding: 10px; display: flex; flex-direction: column; gap: 4px; flex: 1; }
.body b { font-size: 14px; }
.price { color: var(--sa); font-weight: 800; }
.left { color: #d97706; }
.sbtn { border: 0; background: var(--sa); color: #fff; border-radius: 12px; padding: 10px 14px; font: inherit; font-weight: 700; cursor: pointer; display: inline-flex; gap: 6px; align-items: center; justify-content: center; text-decoration: none; }
.sbtn.sm { padding: 6px 10px; font-size: 13px; margin-top: auto; }
.sbtn.big { width: 100%; padding: 14px; font-size: 16px; }
.sbtn.ghost { background: transparent; color: var(--sa); border: 1px solid var(--sa); }
.sbtn.wa { background: #16a34a; }
.sbtn:disabled { opacity: .5; cursor: default; }
.qty { display: inline-flex; align-items: center; gap: 10px; margin-top: auto; }
.qty button { width: 30px; height: 30px; border-radius: 50%; border: 1px solid var(--sa); background: #fff; color: var(--sa); font-size: 18px; cursor: pointer; line-height: 1; }
.cartbar { position: fixed; bottom: 12px; left: 12px; right: 12px; max-width: 836px; margin: 0 auto; background: var(--sa); color: #fff; border-radius: 16px; padding: 14px 18px; display: flex; justify-content: space-between; align-items: center; box-shadow: 0 10px 30px rgba(0, 0, 0, .2); cursor: pointer; }
.checkout h2 { margin: 6px 0 10px; }
.checkout h3 { margin: 16px 0 8px; font-size: 16px; }
.back { border: 0; background: none; color: var(--sa); font: inherit; cursor: pointer; padding: 0; }
.cl { background: #fff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 10px; margin-bottom: 8px; }
.cl .row { display: flex; align-items: center; gap: 10px; }
.cl .qty { margin: 0; }
.lt { min-width: 70px; text-align: left; font-weight: 700; }
.seg { display: flex; gap: 8px; margin-bottom: 10px; }
.seg button { flex: 1; border: 1px solid #e2e8f0; background: #fff; border-radius: 12px; padding: 12px; font: inherit; cursor: pointer; }
.seg button.on { border-color: var(--sa); color: var(--sa); font-weight: 700; background: #fff; box-shadow: inset 0 0 0 1px var(--sa); }
.lbl { font-size: 13px; color: #64748b; display: block; margin-bottom: 4px; }
.sum { background: #fff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 12px; margin: 6px 0 12px; }
.sum div { display: flex; justify-content: space-between; margin: 4px 0; }
.sum .t { font-size: 17px; border-top: 1px dashed #e2e8f0; padding-top: 8px; }
.errbox { background: #fef2f2; color: #b91c1c; border-radius: 12px; padding: 10px 12px; margin-bottom: 10px; }
.done { text-align: center; background: #fff; border-radius: 16px; padding: 28px 16px; display: flex; flex-direction: column; gap: 6px; align-items: center; }
.done > i { font-size: 56px; color: #16a34a; }
.done h2 { margin: 4px 0; }
.muted { color: #64748b; }
.foot { text-align: center; color: #94a3b8; font-size: 12px; padding: 20px 0 0; }
.spin { animation: spin 1s linear infinite; display: inline-block; }
@keyframes spin { to { transform: rotate(360deg); } }
</style>
