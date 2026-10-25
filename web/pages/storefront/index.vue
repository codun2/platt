<script setup lang="ts">
definePageMeta({ title: 'صفحة الطلب أونلاين', titleEn: 'Online Order Page' })
const api = useApi(); const auth = useAuth(); const f = useFmt()
const canManage = computed(() => auth.can('storefront.manage'))
const d = ref<any>(null); const form = reactive<any>({}); const msg = ref(''); const err = ref(''); const saving = ref(false)
const items = ref<any>(null); const iq = ref(''); const icat = ref(''); const cats = ref<any[]>([])
const tab = ref<'settings' | 'items'>('settings')
async function load() { d.value = await api.get('/app/storefront'); Object.assign(form, d.value.settings) }
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

      <template v-else>
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
