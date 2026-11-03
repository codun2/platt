<script setup lang="ts">
/**
 * Public storefront / landing page (add-on «صفحة الطلب أونلاين»). No account.
 * The tenant customizes it from /storefront: logo, colour, theme, layout, hero
 * text, an «about» section, social links, section order, plus the order flow
 * (pick items → pickup/delivery → time). Prices shown are a preview; the server prices it.
 */
definePageMeta({ layout: 'blank', title: 'المتجر', titleEn: 'Store' })
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
    try { Object.assign(form, JSON.parse(localStorage.getItem(KEY) || '{}')) } catch { /* private mode */ }
    if (!d.value.store.pickup) form.fulfilment = 'delivery'
    if (!d.value.store.delivery) form.fulfilment = 'pickup'
  } catch (e: any) { failed.value = e.message === '404' ? tt('هذا المتجر غير موجود أو أُغلق.', 'This store does not exist or is closed.') : tt('تعذّر فتح المتجر، تحقق من الاتصال وحاول مرة أخرى.', 'Could not open the store, check your connection and retry.') }
})

const store = computed(() => d.value?.store || {})
const accent = computed(() => store.value.accent || '#ea580c')
const theme = computed(() => store.value.theme === 'dark' ? 'dark' : 'light')
const layout = computed(() => store.value.layout || 'classic')
// Content blocks in the tenant's order; hero is always first and separate.
const sections = computed<{ key: string; on: boolean }[]>(() => Array.isArray(store.value.sections) ? store.value.sections : [{ key: 'products', on: true }, { key: 'contact', on: true }])
const sectionOn = (k: string) => sections.value.some(s => s.key === k && s.on)

// Social links → { icon, href, label }.
const SOC: Record<string, { icon: string; label: string; url: (v: string) => string }> = {
  instagram: { icon: 'ti-brand-instagram', label: 'Instagram', url: v => v.startsWith('http') ? v : `https://instagram.com/${v.replace(/^@/, '')}` },
  tiktok: { icon: 'ti-brand-tiktok', label: 'TikTok', url: v => v.startsWith('http') ? v : `https://tiktok.com/@${v.replace(/^@/, '')}` },
  snapchat: { icon: 'ti-brand-snapchat', label: 'Snapchat', url: v => v.startsWith('http') ? v : `https://snapchat.com/add/${v.replace(/^@/, '')}` },
  x: { icon: 'ti-brand-x', label: 'X', url: v => v.startsWith('http') ? v : `https://x.com/${v.replace(/^@/, '')}` },
  facebook: { icon: 'ti-brand-facebook', label: 'Facebook', url: v => v.startsWith('http') ? v : `https://facebook.com/${v}` },
  website: { icon: 'ti-world', label: tt('الموقع', 'Website'), url: v => v.startsWith('http') ? v : `https://${v}` },
  email: { icon: 'ti-mail', label: tt('البريد', 'Email'), url: v => `mailto:${v}` },
  maps: { icon: 'ti-map-pin', label: tt('الموقع على الخريطة', 'Map'), url: v => v },
}
const socialList = computed(() => Object.entries(SOC)
  .map(([k, def]) => ({ k, ...def, val: (store.value.socials || {})[k] }))
  .filter(s => s.val && String(s.val).trim())
  .map(s => ({ icon: s.icon, label: s.label, href: s.url(String(s.val).trim()) })))

const shown = computed(() => (d.value?.items || []).filter((i: any) => (!cat.value || i.category_id === cat.value) && (!q.value || i.name.includes(q.value.trim()))))
const catName = (id: number) => (d.value?.categories || []).find((c: any) => c.id === id)?.name || ''
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
const initials = computed(() => (store.value.name || '؟').trim().slice(0, 1))
</script>

<template>
  <div class="store" :class="[theme, `lay-${layout}`]" :style="{ '--sa': accent }">
    <div v-if="failed" class="center"><i class="ti ti-building-store" style="font-size:48px;color:#94a3b8" /><p>{{ failed }}</p></div>
    <div v-else-if="!d" class="center"><i class="ti ti-loader-2 spin" style="font-size:32px" /></div>
    <template v-else>
      <!-- HERO (always first) -->
      <header class="hero">
        <div class="hero-in">
          <div class="logo" :class="{ img: store.logo }"><img v-if="store.logo" :src="store.logo" :alt="store.name" /><span v-else>{{ initials }}</span></div>
          <h1>{{ store.name }}</h1>
          <p v-if="store.tagline" class="tag">{{ store.tagline }}</p>
          <p v-if="store.welcome" class="welcome">{{ store.welcome }}</p>
          <div class="meta">
            <span v-if="store.pickup"><i class="ti ti-building-store" /> {{ tt('استلام من المحل', 'Pickup') }}</span>
            <span v-if="store.delivery"><i class="ti ti-truck-delivery" /> {{ tt('توصيل', 'Delivery') }}<template v-if="+store.delivery_fee">&nbsp;{{ money(store.delivery_fee) }}</template></span>
            <a v-if="waShop" :href="waShop" target="_blank"><i class="ti ti-brand-whatsapp" /> {{ tt('واتساب', 'WhatsApp') }}</a>
          </div>
          <div v-if="socialList.length" class="socials">
            <a v-for="s in socialList" :key="s.label" :href="s.href" target="_blank" rel="noopener" :title="s.label"><i :class="`ti ${s.icon}`" /></a>
          </div>
        </div>
      </header>

      <main class="wrap">
        <!-- Checkout / done take over the content area -->
        <div v-if="step === 'done'" class="done">
          <i class="ti ti-circle-check" />
          <h2>{{ tt('تم استلام طلبك', 'Order received') }}</h2>
          <p>{{ tt('رقم الطلب', 'Order no.') }} <b dir="ltr">{{ done.number }}</b></p>
          <p>{{ done.fulfilment === 'delivery' ? tt('موعد التوصيل', 'Delivery') : tt('موعد الاستلام', 'Pickup') }}: <b dir="ltr">{{ done.due_at }}</b> · {{ tt('الإجمالي', 'Total') }} <b>{{ money(done.total) }}</b></p>
          <p class="muted">{{ tt('سيتواصل معك المحل لتأكيد الطلب. الدفع عند الاستلام.', 'The shop will contact you to confirm. Pay on pickup / delivery.') }}</p>
          <a v-if="waShop" :href="waShop" target="_blank" class="sbtn wa"><i class="ti ti-brand-whatsapp" /> {{ tt('أرسل رقم الطلب للمحل على واتساب', 'Send the order number on WhatsApp') }}</a>
          <button class="sbtn ghost" @click="step = 'menu'">{{ tt('طلب جديد', 'New order') }}</button>
        </div>

        <div v-else-if="step === 'checkout'" class="checkout card">
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
            <button v-if="store.pickup" :class="{ on: form.fulfilment === 'pickup' }" @click="form.fulfilment = 'pickup'"><i class="ti ti-building-store" /> {{ tt('استلام من المحل', 'Pickup') }}</button>
            <button v-if="store.delivery" :class="{ on: form.fulfilment === 'delivery' }" @click="form.fulfilment = 'delivery'"><i class="ti ti-truck-delivery" /> {{ tt('توصيل', 'Delivery') }}</button>
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

        <!-- MENU: content sections in the tenant's order -->
        <template v-else>
          <template v-for="sec in sections" :key="sec.key">
            <!-- PRODUCTS -->
            <section v-if="sec.key === 'products' && sec.on" class="block">
              <div v-if="!store.open" class="note"><i class="ti ti-clock-pause" /> {{ tt('المتجر لا يستقبل طلبات أونلاين حالياً.', 'The store is not taking online orders right now.') }}</div>
              <template v-else>
                <div v-if="store.notice" class="note"><i class="ti ti-info-circle" /> {{ store.notice }}</div>
                <input v-model="q" class="search" :placeholder="tt('ابحث عن صنف…', 'Search…')" />
                <div v-if="d.categories.length" class="cats">
                  <button :class="{ on: !cat }" @click="cat = ''">{{ tt('الكل', 'All') }}</button>
                  <button v-for="c in d.categories" :key="c.id" :class="{ on: cat === c.id }" @click="cat = c.id">{{ c.name }}</button>
                </div>
                <div class="grid">
                  <article v-for="i in shown" :key="i.id" class="item" :class="{ in: cart[i.id] }">
                    <div class="img" :class="{ 'no-img': !i.image }" :style="i.image ? { backgroundImage: `url(${i.image})` } : {}">
                      <i v-if="!i.image" class="ti ti-shopping-bag ph" />
                      <span v-if="i.left !== null && i.left <= 5" class="stock">{{ tt(`متبقٍ ${i.left}`, `${i.left} left`) }}</span>
                    </div>
                    <div class="ibody">
                      <span v-if="catName(i.category_id)" class="cat">{{ catName(i.category_id) }}</span>
                      <b class="nm">{{ i.name }}</b>
                      <span class="price">{{ money(i.price) }}<small v-if="i.unit"> / {{ i.unit }}</small></span>
                      <div v-if="cart[i.id]" class="qty"><button @click="add(i, -1)">−</button><b>{{ cart[i.id].qty }}</b><button @click="add(i, 1)">+</button></div>
                      <button v-else class="sbtn sm" @click="add(i)"><i class="ti ti-plus" /> {{ tt('أضف', 'Add') }}</button>
                    </div>
                  </article>
                </div>
                <p v-if="!shown.length" class="center muted">{{ tt('لا توجد أصناف.', 'No items.') }}</p>
              </template>
            </section>

            <!-- ABOUT -->
            <section v-else-if="sec.key === 'about' && sec.on && store.about" class="block card about">
              <h2><i class="ti ti-info-circle" /> {{ tt('نبذة عنّا', 'About us') }}</h2>
              <p>{{ store.about }}</p>
            </section>

            <!-- CONTACT -->
            <section v-else-if="sec.key === 'contact' && sec.on" class="block card contact">
              <h2><i class="ti ti-address-book" /> {{ tt('تواصل معنا', 'Contact us') }}</h2>
              <div class="clist">
                <a v-if="store.whatsapp" :href="waShop" target="_blank" class="crow"><i class="ti ti-brand-whatsapp" /><span dir="ltr">{{ store.whatsapp }}</span></a>
                <a v-if="store.phone" :href="`tel:${store.phone}`" class="crow"><i class="ti ti-phone" /><span dir="ltr">{{ store.phone }}</span></a>
                <div v-if="store.address" class="crow"><i class="ti ti-map-pin" /><span>{{ store.address }}</span></div>
              </div>
              <div v-if="socialList.length" class="socials dark-ok">
                <a v-for="s in socialList" :key="s.label" :href="s.href" target="_blank" rel="noopener" :title="s.label"><i :class="`ti ${s.icon}`" /></a>
              </div>
            </section>
          </template>
        </template>
      </main>

      <div v-if="step === 'menu' && count" class="cartbar" @click="step = 'checkout'">
        <span><i class="ti ti-shopping-bag" /> {{ count }} {{ tt('صنف', 'items') }} · {{ money(subtotal) }}</span>
        <b v-if="belowMin">{{ tt('الحد الأدنى', 'Minimum') }} {{ money(store.min_order) }}</b>
        <b v-else>{{ tt('متابعة الطلب', 'Checkout') }} <i class="ti ti-chevron-left" /></b>
      </div>
      <footer class="foot">{{ tt('مدعوم من', 'Powered by') }} <b>ركائز</b></footer>
    </template>
  </div>
</template>

<style scoped>
/* Theme tokens: light default, dark override via .store.dark */
.store { --bg: #f6f8fc; --card: #fff; --ink: #0f172a; --muted: #64748b; --line: #e6ebf3;
  min-height: 100vh; background: var(--bg); color: var(--ink); font-family: 'Cairo', system-ui, sans-serif; padding-bottom: 90px; }
.store.dark { --bg: #0e1420; --card: #161f2d; --ink: #e7ebf5; --muted: #93a0b8; --line: #273247; }
.center { text-align: center; padding: 80px 16px; color: var(--muted); }

/* ---- hero ---- */
.hero { background: var(--sa); color: #fff; padding: 30px 16px 26px; text-align: center; }
.lay-modern .hero { background: linear-gradient(135deg, var(--sa), color-mix(in srgb, var(--sa) 55%, #000)); border-radius: 0 0 26px 26px; }
.lay-minimal .hero { background: var(--card); color: var(--ink); border-bottom: 1px solid var(--line); }
.hero-in { max-width: 860px; margin: 0 auto; }
.logo { width: 76px; height: 76px; border-radius: 20px; margin: 0 auto 14px; display: grid; place-items: center;
  background: rgba(255,255,255,.18); border: 1px solid rgba(255,255,255,.3); font-size: 34px; font-weight: 900; overflow: hidden; }
.lay-minimal .logo { background: var(--sa); color: #fff; border: 0; }
.logo.img { background: #fff; }
.logo img { width: 100%; height: 100%; object-fit: cover; }
.hero h1 { margin: 0; font-size: 26px; font-weight: 900; }
.hero .tag { margin: 6px 0 0; font-size: 15px; opacity: .95; font-weight: 700; }
.hero .welcome { margin: 8px 0 0; opacity: .9; }
.meta { display: flex; gap: 14px; justify-content: center; flex-wrap: wrap; margin-top: 14px; font-size: 13px; }
.meta a { color: inherit; text-decoration: none; }
.lay-minimal .meta { color: var(--muted); }
.socials { display: flex; gap: 10px; justify-content: center; margin-top: 16px; flex-wrap: wrap; }
.socials a { width: 38px; height: 38px; border-radius: 11px; display: grid; place-items: center; font-size: 19px;
  background: rgba(255,255,255,.18); color: #fff; text-decoration: none; transition: transform .15s; }
.socials a:hover { transform: translateY(-2px); }
.lay-minimal .socials a { background: var(--sa); }
.socials.dark-ok a { background: color-mix(in srgb, var(--sa) 14%, transparent); color: var(--sa); }

/* ---- layout ---- */
.wrap { max-width: 860px; margin: 0 auto; padding: 14px 16px; }
.block { margin-bottom: 18px; }
.card { background: var(--card); border: 1px solid var(--line); border-radius: 16px; padding: 18px; }
.about h2, .contact h2 { margin: 0 0 10px; font-size: 18px; display: flex; align-items: center; gap: 8px; }
.about h2 i, .contact h2 i { color: var(--sa); }
.about p { margin: 0; color: var(--muted); line-height: 1.9; white-space: pre-line; }
.clist { display: flex; flex-direction: column; gap: 8px; }
.crow { display: flex; align-items: center; gap: 10px; color: var(--ink); text-decoration: none; font-weight: 600; }
.crow i { color: var(--sa); font-size: 20px; width: 24px; text-align: center; }

.note { background: color-mix(in srgb, var(--sa) 10%, var(--card)); color: var(--ink); border: 1px solid var(--line); border-radius: 12px; padding: 10px 12px; margin-bottom: 12px; font-size: 14px; }
.search, .inp { width: 100%; box-sizing: border-box; padding: 12px 14px; border: 1px solid var(--line); border-radius: 12px; font: inherit; font-size: 15px; background: var(--card); color: var(--ink); margin-bottom: 10px; }
.inp.sm { padding: 8px 10px; font-size: 13px; margin: 6px 0 0; }
.cats { display: flex; gap: 8px; overflow-x: auto; padding-bottom: 6px; margin-bottom: 8px; }
.cats button { flex-shrink: 0; border: 1px solid var(--line); background: var(--card); color: var(--ink); border-radius: 999px; padding: 6px 14px; font: inherit; font-size: 13px; cursor: pointer; }
.cats button.on { background: var(--sa); border-color: var(--sa); color: #fff; }
.grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(168px, 1fr)); gap: 14px; }
.item { background: var(--card); border: 1px solid var(--line); border-radius: 16px; overflow: hidden; display: flex; flex-direction: column; transition: transform .18s ease, box-shadow .18s ease, border-color .18s ease; }
.item:hover { transform: translateY(-4px); box-shadow: 0 18px 38px -22px rgba(15,23,42,.5); border-color: color-mix(in srgb, var(--sa) 45%, var(--line)); }
.lay-modern .item { box-shadow: 0 10px 26px -18px rgba(15,23,42,.4); }
.item.in { border-color: var(--sa); box-shadow: 0 0 0 1px var(--sa) inset; }
.img { position: relative; height: 180px; background: var(--bg) center / cover; display: grid; place-items: center; color: #cbd5e1; font-size: 32px; overflow: hidden; }
.img::after { content: ''; position: absolute; inset: 0; background: linear-gradient(180deg, rgba(15,23,42,.04), transparent 30%); pointer-events: none; }
/* Imageless products get an elegant accent-tinted tile instead of an empty gray box. */
.img.no-img { background: linear-gradient(135deg, color-mix(in srgb, var(--sa) 15%, var(--card)), color-mix(in srgb, var(--sa) 4%, var(--card))); }
.img.no-img .ph { font-size: 42px; color: color-mix(in srgb, var(--sa) 55%, #cbd5e1); opacity: .7; }
.img .ph { opacity: .55; }
.lay-modern .img { height: 200px; }
.img .stock { position: absolute; top: 8px; inset-inline-start: 8px; z-index: 1; background: color-mix(in srgb, #d97706 92%, #000); color: #fff; font-size: 11px; font-weight: 800; padding: 3px 9px; border-radius: 999px; box-shadow: 0 4px 10px rgba(0,0,0,.2); }
.ibody { padding: 12px; display: flex; flex-direction: column; gap: 5px; flex: 1; }
.cat { font-size: 11px; font-weight: 800; color: var(--sa); letter-spacing: .2px; }
.nm { font-size: 14.5px; font-weight: 700; line-height: 1.35; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }
.price { color: var(--sa); font-weight: 800; font-size: 15px; margin-top: 2px; }
.price small { color: var(--muted); font-weight: 600; font-size: 12px; }
.sbtn { border: 0; background: var(--sa); color: #fff; border-radius: 12px; padding: 10px 14px; font: inherit; font-weight: 700; cursor: pointer; display: inline-flex; gap: 6px; align-items: center; justify-content: center; text-decoration: none; }
.sbtn.sm { padding: 6px 10px; font-size: 13px; margin-top: auto; }
.sbtn.big { width: 100%; padding: 14px; font-size: 16px; }
.sbtn.ghost { background: transparent; color: var(--sa); border: 1px solid var(--sa); }
.sbtn.wa { background: #16a34a; }
.sbtn:disabled { opacity: .5; cursor: default; }
.qty { display: inline-flex; align-items: center; gap: 10px; margin-top: auto; }
.qty button { width: 30px; height: 30px; border-radius: 50%; border: 1px solid var(--sa); background: var(--card); color: var(--sa); font-size: 18px; cursor: pointer; line-height: 1; }
.cartbar { position: fixed; bottom: 12px; left: 12px; right: 12px; max-width: 836px; margin: 0 auto; background: var(--sa); color: #fff; border-radius: 16px; padding: 14px 18px; display: flex; justify-content: space-between; align-items: center; box-shadow: 0 10px 30px rgba(0, 0, 0, .25); cursor: pointer; }
.checkout h2 { margin: 6px 0 10px; }
.checkout h3 { margin: 16px 0 8px; font-size: 16px; }
.back { border: 0; background: none; color: var(--sa); font: inherit; cursor: pointer; padding: 0; }
.cl { background: var(--card); border: 1px solid var(--line); border-radius: 12px; padding: 10px; margin-bottom: 8px; }
.cl .row { display: flex; align-items: center; gap: 10px; }
.cl .qty { margin: 0; }
.lt { min-width: 70px; text-align: left; font-weight: 700; }
.seg { display: flex; gap: 8px; margin-bottom: 10px; }
.seg button { flex: 1; border: 1px solid var(--line); background: var(--card); color: var(--ink); border-radius: 12px; padding: 12px; font: inherit; cursor: pointer; }
.seg button.on { border-color: var(--sa); color: var(--sa); font-weight: 700; box-shadow: inset 0 0 0 1px var(--sa); }
.lbl { font-size: 13px; color: var(--muted); display: block; margin-bottom: 4px; }
.sum { background: var(--bg); border: 1px solid var(--line); border-radius: 12px; padding: 12px; margin: 6px 0 12px; }
.sum div { display: flex; justify-content: space-between; margin: 4px 0; }
.sum .t { font-size: 17px; border-top: 1px dashed var(--line); padding-top: 8px; }
.errbox { background: #fef2f2; color: #b91c1c; border-radius: 12px; padding: 10px 12px; margin-bottom: 10px; }
.done { text-align: center; background: var(--card); border: 1px solid var(--line); border-radius: 16px; padding: 28px 16px; display: flex; flex-direction: column; gap: 6px; align-items: center; }
.done > i { font-size: 56px; color: #16a34a; }
.done h2 { margin: 4px 0; }
.muted { color: var(--muted); }
.foot { text-align: center; color: var(--muted); font-size: 12px; padding: 20px 0 0; }
.spin { animation: spin 1s linear infinite; display: inline-block; }
@keyframes spin { to { transform: rotate(360deg); } }
</style>
