<script setup lang="ts">
definePageMeta({ title: 'صفحة الطلب أونلاين', titleEn: 'Online Order Page' })
const api = useApi(); const auth = useAuth(); const f = useFmt(); const config = useRuntimeConfig()
const canManage = computed(() => auth.can('storefront.manage'))
const d = ref<any>(null); const form = reactive<any>({}); const msg = ref(''); const err = ref(''); const saving = ref(false)
const items = ref<any>(null); const iq = ref(''); const icat = ref(''); const cats = ref<any[]>([])
const tab = ref<'settings' | 'design' | 'items'>('settings')
const logoBusy = ref(false)

// Social networks shown in the design tab.
const SOCIALS = [
  { k: 'instagram', icon: 'ti-brand-instagram', label: 'Instagram', ph: '@yourshop' },
  { k: 'tiktok', icon: 'ti-brand-tiktok', label: 'TikTok', ph: '@yourshop' },
  { k: 'snapchat', icon: 'ti-brand-snapchat', label: 'Snapchat', ph: '@yourshop' },
  { k: 'x', icon: 'ti-brand-x', label: 'X', ph: '@yourshop' },
  { k: 'facebook', icon: 'ti-brand-facebook', label: 'Facebook', ph: 'facebook.com/…' },
  { k: 'website', icon: 'ti-world', label: tt('الموقع', 'Website'), ph: 'example.com' },
  { k: 'email', icon: 'ti-mail', label: tt('البريد', 'Email'), ph: 'shop@example.com' },
  { k: 'maps', icon: 'ti-map-pin', label: tt('خرائط', 'Maps'), ph: 'https://maps.google.com/…' },
]
const SECTION_LABELS: Record<string, { ar: string; en: string; icon: string }> = {
  products: { ar: 'المنتجات والأصناف', en: 'Products', icon: 'ti-box' },
  about: { ar: 'نبذة عنّا', en: 'About us', icon: 'ti-info-circle' },
  contact: { ar: 'تواصل معنا', en: 'Contact', icon: 'ti-address-book' },
}
const logoPreview = computed(() => form.logo ? (String(form.logo).startsWith('http') ? form.logo : `/storage/${form.logo}`) : '')

async function load() {
  d.value = await api.get('/app/storefront')
  Object.assign(form, d.value.settings)
  // socials must be an object for v-model; sections an array.
  if (!form.socials || Array.isArray(form.socials)) form.socials = {}
  if (!Array.isArray(form.sections)) form.sections = []
}
function moveSection(i: number, dir: number) {
  const j = i + dir
  if (j < 0 || j >= form.sections.length) return
  const a = form.sections
  ;[a[i], a[j]] = [a[j], a[i]]
}
async function uploadLogo(e: Event) {
  const file = (e.target as HTMLInputElement).files?.[0]
  if (!file) return
  logoBusy.value = true; msg.value = ''; err.value = ''
  try {
    const fd = new FormData(); fd.append('logo', file)
    const token = (() => { try { return localStorage.getItem('rakeez_token') } catch { return '' } })()
    const r = await fetch(`${config.public.apiBase}/app/storefront/logo`, { method: 'POST', headers: { Authorization: `Bearer ${token}`, Accept: 'application/json', 'X-Locale': currentLocale() }, body: fd })
    const j = await r.json().catch(() => ({}))
    if (!r.ok) throw new Error((Object.values(j.errors || {})[0] as any)?.[0] || j.message || tt('تعذّر رفع الشعار', 'Could not upload the logo'))
    form.logo = j.logo; msg.value = tt('تم رفع الشعار', 'Logo uploaded')
  } catch (ex: any) { err.value = ex.message } finally { logoBusy.value = false; (e.target as HTMLInputElement).value = '' }
}
async function removeLogo() {
  if (!form.logo) return
  try { await api.raw('/app/storefront/logo', { method: 'DELETE' }); form.logo = null; msg.value = tt('تم حذف الشعار', 'Logo removed') } catch (e: any) { uiAlert(e.message) }
}
async function loadItems(p: any = 1) { items.value = await api.get('/app/storefront/items', { q: iq.value, category_id: icat.value, page: typeof p === 'number' ? p : 1 }) }
onMounted(async () => { await load(); loadItems(); try { cats.value = (await api.get('/app/inventory/items/meta')).categories || [] } catch { /* no inventory */ } })
const link = computed(() => d.value ? `${location.origin}/app/store/${d.value.settings.handle}` : '')
async function save() {
  msg.value = ''; err.value = ''; saving.value = true
  try { d.value = await api.raw('/app/storefront', { method: 'PUT', body: { ...form, handle: String(form.handle || '').toLowerCase() } }); Object.assign(form, d.value.settings); msg.value = tt('تم حفظ إعدادات الصفحة', 'Saved') }
  catch (e: any) { err.value = e.message } finally { saving.value = false }
}
async function setShow(ids: number[], show: boolean) {
  try { await api.post('/app/storefront/items', { ids, show }); await Promise.all([loadItems(items.value?.current_page || 1), load()]) } catch (e: any) { uiAlert(e.message) }
}
async function copy() { try { await navigator.clipboard.writeText(link.value); msg.value = tt('تم نسخ الرابط', 'Link copied') } catch { uiAlert(link.value) } }
const shareWa = () => window.open(`https://wa.me/?text=${encodeURIComponent(tt(`اطلب من ${form.title || ''} أونلاين: ${link.value}`, `Order online: ${link.value}`))}`, '_blank')
</script>

<template>
  <div v-if="d">
    <div class="grid grid-4">
      <div class="card stat"><div class="ic" :style="{ background: d.settings.open ? 'var(--ok)' : 'var(--muted)' }"><i :class="`ti ${d.settings.open ? 'ti-world-check' : 'ti-world-off'}`" /></div><div><b>{{ d.settings.open ? tt('مفتوحة للطلبات', 'Open for orders') : tt('مغلقة', 'Closed') }}</b><small>{{ tt('حالة الصفحة', 'Page status') }}</small></div></div>
      <div class="card stat"><div class="ic" style="background:var(--info)"><i class="ti ti-shopping-bag" /></div><div><b>{{ d.stats.orders_month }}</b><small>{{ tt('طلبات أونلاين هذا الشهر', 'Online orders this month') }} · {{ f.money(d.stats.value_month) }}</small></div></div>
      <div class="card stat"><div class="ic" style="background:var(--primary)"><i class="ti ti-bell" /></div><div><b>{{ d.stats.orders_new }}</b><small><NuxtLink to="/orders">{{ tt('طلبات جديدة بانتظار التأكيد', 'New orders to confirm') }}</NuxtLink></small></div></div>
      <div class="card stat"><div class="ic" style="background:var(--warn)"><i class="ti ti-box" /></div><div><b>{{ d.stats.items_shown }} / {{ d.stats.items_total }}</b><small>{{ tt('أصناف ظاهرة في الصفحة', 'Items on the page') }}</small></div></div>
    </div>

    <div class="card mt">
      <div class="card-title"><i class="ti ti-link" /> {{ tt('رابط متجرك', 'Your store link') }}</div>
      <div class="row" style="gap:8px;flex-wrap:wrap">
        <input :value="link" readonly dir="ltr" style="flex:1;min-width:260px;padding:10px 12px;border:1px solid var(--line);border-radius:10px;background:var(--bg)" />
        <button class="btn ghost" @click="copy"><i class="ti ti-copy" /> {{ tt('نسخ', 'Copy') }}</button>
        <a :href="link" target="_blank" class="btn ghost"><i class="ti ti-external-link" /> {{ tt('فتح الصفحة', 'Open') }}</a>
        <button class="btn ok" @click="shareWa"><i class="ti ti-brand-whatsapp" /> {{ tt('مشاركة', 'Share') }}</button>
      </div>
      <small class="muted">{{ tt('ضعه في حساب إنستغرام والحالة وقوائم واتساب. الطلبات تصل إلى «الطلبات المسبقة» كطلب أونلاين جديد.', 'Put it on Instagram, WhatsApp status and broadcasts. Orders arrive in Pre-orders as new online orders.') }}</small>
    </div>

    <div class="card mt">
      <div class="tabs">
        <button :class="{ on: tab === 'settings' }" @click="tab = 'settings'"><i class="ti ti-settings" /> {{ tt('الإعدادات', 'Settings') }}</button>
        <button :class="{ on: tab === 'design' }" @click="tab = 'design'"><i class="ti ti-palette" /> {{ tt('تخصيص الصفحة', 'Customize page') }}</button>
        <button :class="{ on: tab === 'items' }" @click="tab = 'items'"><i class="ti ti-box" /> {{ tt('الأصناف الظاهرة', 'Shown items') }}</button>
      </div>
      <template v-if="tab === 'settings'">
        <div v-if="msg" class="alert ok">{{ msg }}</div>
        <div v-if="err" class="alert bad">{{ err }}</div>
        <label class="row" style="gap:10px;margin-bottom:12px"><span class="switch"><input v-model="form.open" type="checkbox" :disabled="!canManage" /><span /></span> <b>{{ tt('الصفحة تستقبل الطلبات', 'Page accepts orders') }}</b></label>
        <div class="grid grid-3">
          <div class="field"><label>{{ tt('اسم الرابط', 'Link name') }}</label><input v-model="form.handle" dir="ltr" :disabled="!canManage" placeholder="halawiyat-alsham" /><small class="muted" dir="ltr">/app/store/{{ form.handle }}</small></div>
          <div class="field"><label>{{ tt('العنوان الظاهر', 'Title') }}</label><input v-model="form.title" :disabled="!canManage" /></div>
          <div class="field"><label>{{ tt('واتساب المحل', 'Shop WhatsApp') }}</label><input v-model="form.whatsapp" dir="ltr" :disabled="!canManage" placeholder="05xxxxxxxx" /></div>
          <div class="field" style="grid-column:span 2"><label>{{ tt('جملة ترحيب', 'Welcome line') }}</label><input v-model="form.welcome" :disabled="!canManage" :placeholder="tt('مثال: حلويات طازجة يومياً — اطلب واستلم بدون انتظار', 'e.g. Fresh daily — order ahead, skip the queue')" /></div>
          <div class="field"><label>{{ tt('وقت التجهيز (ساعات)', 'Lead time (hours)') }}</label><input v-model.number="form.lead_hours" type="number" min="0" step="0.5" dir="ltr" :disabled="!canManage" /><small class="muted">{{ tt('أقرب موعد يختاره العميل', 'Earliest time a customer can pick') }}</small></div>
        </div>
        <div class="grid grid-4">
          <div class="field"><label class="row" style="gap:8px"><span class="switch"><input v-model="form.pickup" type="checkbox" :disabled="!canManage" /><span /></span> {{ tt('استلام من المحل', 'Pickup') }}</label></div>
          <div class="field"><label class="row" style="gap:8px"><span class="switch"><input v-model="form.delivery" type="checkbox" :disabled="!canManage" /><span /></span> {{ tt('توصيل', 'Delivery') }}</label></div>
          <div class="field"><label>{{ tt('رسوم التوصيل', 'Delivery fee') }}</label><input v-model.number="form.delivery_fee" type="number" min="0" step="0.001" dir="ltr" :disabled="!canManage || !form.delivery" /></div>
          <div class="field"><label>{{ tt('أقل قيمة للطلب', 'Minimum order') }}</label><input v-model.number="form.min_order" type="number" min="0" step="0.001" dir="ltr" :disabled="!canManage" /></div>
        </div>
        <label class="row" style="gap:8px;margin-bottom:12px"><span class="switch"><input v-model="form.hide_out_of_stock" type="checkbox" :disabled="!canManage" /><span /></span> {{ tt('إخفاء الأصناف التي نفد مخزونها', 'Hide out-of-stock items') }}</label>
        <div class="field"><label>{{ tt('تنبيه يظهر أعلى الصفحة', 'Notice on top of the page') }}</label><input v-model="form.notice" :disabled="!canManage" :placeholder="tt('مثال: طلبات الكيك قبل يوم على الأقل', 'e.g. Cake orders one day ahead')" /></div>
        <button v-if="canManage" class="btn" :disabled="saving" @click="save"><i class="ti ti-device-floppy" /> {{ tt('حفظ', 'Save') }}</button>
      </template>

      <!-- ===== Customize page ===== -->
      <template v-else-if="tab === 'design'">
        <div v-if="msg" class="alert ok">{{ msg }}</div>
        <div v-if="err" class="alert bad">{{ err }}</div>

        <!-- Brand: logo + colour + theme + layout -->
        <div class="grid grid-2" style="align-items:start">
          <div>
            <div class="field"><label>{{ tt('شعار المتجر', 'Store logo') }}</label>
              <div class="row" style="gap:12px;align-items:center">
                <div class="logo-box"><img v-if="logoPreview" :src="logoPreview" alt="logo" /><i v-else class="ti ti-building-store" /></div>
                <div class="row" style="gap:8px;flex-wrap:wrap">
                  <label class="btn ghost sm" :class="{ disabled: !canManage || logoBusy }"><i :class="`ti ${logoBusy ? 'ti-loader-2 spin' : 'ti-upload'}`" /> {{ form.logo ? tt('تغيير', 'Change') : tt('رفع شعار', 'Upload') }}<input type="file" accept="image/png,image/jpeg,image/webp,image/svg+xml" hidden :disabled="!canManage || logoBusy" @change="uploadLogo" /></label>
                  <button v-if="form.logo" class="btn ghost sm" :disabled="!canManage" @click="removeLogo"><i class="ti ti-trash" /> {{ tt('حذف', 'Remove') }}</button>
                </div>
              </div>
              <small class="muted">{{ tt('PNG أو JPG أو SVG، حتى 2 ميجابايت.', 'PNG, JPG or SVG, up to 2MB.') }}</small>
            </div>
          </div>
          <div>
            <div class="field"><label>{{ tt('لون الثيم', 'Theme colour') }}</label>
              <div class="row" style="gap:10px;align-items:center">
                <input type="color" class="color" :value="form.accent || '#ea580c'" :disabled="!canManage" @input="form.accent = ($event.target as HTMLInputElement).value">
                <input v-model="form.accent" dir="ltr" placeholder="#ea580c" style="width:130px" :disabled="!canManage" />
                <button v-if="form.accent" class="btn ghost sm" :disabled="!canManage" @click="form.accent = null"><i class="ti ti-rotate" /> {{ tt('لون القطاع', 'Sector colour') }}</button>
              </div>
              <small class="muted">{{ tt('يُطبّق على أزرار الصفحة وترويستها. فارغ = لون القطاع الافتراضي.', 'Applied to the page buttons and hero. Empty = default sector colour.') }}</small>
            </div>
            <div class="grid grid-2">
              <div class="field"><label>{{ tt('المظهر', 'Theme') }}</label><UiSelect v-model="form.theme" :disabled="!canManage"><option value="light">{{ tt('فاتح', 'Light') }}</option><option value="dark">{{ tt('داكن', 'Dark') }}</option></UiSelect></div>
              <div class="field"><label>{{ tt('شكل الصفحة', 'Layout') }}</label><UiSelect v-model="form.layout" :disabled="!canManage"><option value="classic">{{ tt('كلاسيكي', 'Classic') }}</option><option value="modern">{{ tt('عصري', 'Modern') }}</option><option value="minimal">{{ tt('مبسّط', 'Minimal') }}</option></UiSelect></div>
            </div>
          </div>
        </div>

        <!-- Hero text -->
        <div class="grid grid-2">
          <div class="field"><label>{{ tt('شعار نصّي (سطر قصير تحت الاسم)', 'Tagline (short line under the name)') }}</label><input v-model="form.tagline" :disabled="!canManage" :placeholder="tt('مثال: أجود الحلويات الشامية منذ 1990', 'e.g. Finest sweets since 1990')" /></div>
          <div class="field"><label>{{ tt('نبذة عنّا (قسم كامل)', 'About us (full section)') }}</label><textarea v-model="form.about" rows="2" :disabled="!canManage" :placeholder="tt('عرّف بمتجرك، قصّته، وما يميّزه…', 'Introduce your store, its story, what makes it special…')" style="width:100%;padding:9px 12px;border:1px solid var(--line);border-radius:10px;background:var(--field-bg);font:inherit" /></div>
        </div>

        <!-- Social links -->
        <div class="field"><label>{{ tt('وسائل التواصل', 'Social links') }}</label>
          <div class="grid grid-4 socials-grid">
            <div v-for="s in SOCIALS" :key="s.k" class="soc"><i :class="`ti ${s.icon}`" /><input v-model="form.socials[s.k]" dir="ltr" :placeholder="s.ph" :disabled="!canManage" /></div>
          </div>
        </div>

        <!-- Section order -->
        <div class="field"><label>{{ tt('ترتيب أقسام الصفحة', 'Section order') }}</label>
          <small class="muted" style="display:block;margin-bottom:8px">{{ tt('الترويسة (الاسم والشعار ووسائل التواصل) تظهر دائماً في الأعلى. رتّب الباقي وأظهر/أخفِ ما تريد.', 'The hero (name, logo, socials) is always on top. Reorder the rest and show/hide each.') }}</small>
          <div class="sec-list">
            <div v-for="(s, i) in form.sections" :key="s.key" class="sec-row">
              <div class="sec-ord">
                <button :disabled="!canManage || i === 0" @click="moveSection(i, -1)"><i class="ti ti-chevron-up" /></button>
                <button :disabled="!canManage || i === form.sections.length - 1" @click="moveSection(i, 1)"><i class="ti ti-chevron-down" /></button>
              </div>
              <i :class="`ti ${SECTION_LABELS[s.key]?.icon}`" class="sec-ic" />
              <b style="flex:1">{{ tt(SECTION_LABELS[s.key]?.ar, SECTION_LABELS[s.key]?.en) }}</b>
              <label class="switch"><input v-model="s.on" type="checkbox" :disabled="!canManage" /><span /></label>
            </div>
          </div>
        </div>

        <div class="row" style="gap:10px;margin-top:4px">
          <button v-if="canManage" class="btn" :disabled="saving" @click="save"><i class="ti ti-device-floppy" /> {{ tt('حفظ', 'Save') }}</button>
          <a :href="link" target="_blank" class="btn ghost"><i class="ti ti-eye" /> {{ tt('معاينة الصفحة', 'Preview') }}</a>
        </div>
      </template>

      <template v-else-if="tab === 'items'">
        <div class="row" style="gap:8px;margin-bottom:12px;flex-wrap:wrap">
          <input v-model="iq" :placeholder="tt('ابحث عن صنف', 'Search item')" style="padding:8px 12px;border:1px solid var(--line);border-radius:10px;min-width:220px" @keyup.enter="loadItems()" />
          <UiSelect v-model="icat" style="min-width:180px" @change="loadItems()"><option value="">{{ tt('كل التصنيفات', 'All categories') }}</option><option v-for="c in cats" :key="c.id" :value="c.id">{{ c.name }}</option></UiSelect>
          <template v-if="canManage && items?.data.length">
            <button class="btn ghost sm" @click="setShow(items.data.map((i: any) => i.id), true)"><i class="ti ti-eye" /> {{ tt('إظهار الكل في هذه الصفحة', 'Show all on this page') }}</button>
            <button class="btn ghost sm" @click="setShow(items.data.map((i: any) => i.id), false)"><i class="ti ti-eye-off" /> {{ tt('إخفاء الكل', 'Hide all') }}</button>
          </template>
        </div>
        <table class="table">
          <thead><tr><th>{{ tt('الصنف', 'Item') }}</th><th>{{ tt('التصنيف', 'Category') }}</th><th>{{ tt('السعر', 'Price') }}</th><th>{{ tt('المخزون', 'Stock') }}</th><th>{{ tt('يظهر أونلاين', 'Shown online') }}</th></tr></thead>
          <tbody>
            <tr v-for="i in items?.data" :key="i.id">
              <td><b>{{ i.name }}</b><br /><small class="muted" dir="ltr">{{ i.sku }}</small></td>
              <td>{{ i.category?.name || '—' }}</td>
              <td class="num">{{ f.money(i.sale_price) }}<br /><small v-if="!(+i.sale_price > 0)" style="color:var(--warn)">{{ tt('بلا سعر — لا يظهر', 'No price — hidden') }}</small></td>
              <td class="num">{{ i.track_stock ? f.qty(i.stock || 0) : '—' }}</td>
              <td><label class="switch"><input type="checkbox" :checked="i.show_online" :disabled="!canManage" @change="setShow([i.id], !i.show_online)" /><span /></label></td>
            </tr>
          </tbody>
        </table>
        <div v-if="items?.data.length" class="mt"><UiPager :p="items" @go="loadItems" /></div>
      </template>
    </div>
  </div>
</template>

<style scoped>
.logo-box { width: 64px; height: 64px; border-radius: 14px; border: 1px solid var(--line); background: var(--bg); display: grid; place-items: center; overflow: hidden; flex-shrink: 0; color: var(--muted); font-size: 26px; }
.logo-box img { width: 100%; height: 100%; object-fit: cover; }
.btn.disabled { opacity: .6; pointer-events: none; }
.color { width: 46px; height: 38px; padding: 2px; border: 1px solid var(--line); border-radius: 10px; background: var(--card); cursor: pointer; }
.socials-grid { gap: 10px; }
@media (max-width: 700px) { .socials-grid { grid-template-columns: 1fr 1fr; } }
.soc { display: flex; align-items: center; gap: 8px; border: 1px solid var(--line); border-radius: 10px; padding: 4px 10px; background: var(--field-bg); }
.soc i { color: var(--muted); font-size: 18px; flex-shrink: 0; }
.soc input { border: 0; background: transparent; padding: 7px 0; width: 100%; font: inherit; color: var(--ink); }
.soc input:focus { outline: none; }
.sec-list { display: flex; flex-direction: column; gap: 8px; }
.sec-row { display: flex; align-items: center; gap: 12px; border: 1px solid var(--line); border-radius: 12px; padding: 10px 12px; background: var(--card); }
.sec-ic { color: var(--primary); font-size: 20px; }
.sec-ord { display: flex; flex-direction: column; gap: 2px; }
.sec-ord button { width: 26px; height: 20px; display: grid; place-items: center; border: 1px solid var(--line); background: var(--card); color: var(--muted); border-radius: 6px; cursor: pointer; }
.sec-ord button:disabled { opacity: .4; cursor: default; }
.sec-ord button:hover:not(:disabled) { color: var(--primary); border-color: var(--primary); }
</style>
