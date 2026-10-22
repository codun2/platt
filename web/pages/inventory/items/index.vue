<script setup lang="ts">
definePageMeta({ title: 'الأصناف', titleEn: 'Items' })
const api = useApi(); const auth = useAuth(); const f = useFmt(); const { tt } = useI18n()
const page = ref<any>(null); const meta = ref<any>({ categories: [], units: [], warehouses: [] }); const pg = ref(1)
const filt = reactive({ q: '', category_id: '', low_stock: false })
const sub = ref<'cat' | 'unit' | null>(null); const subName = ref(''); const subSym = ref(''); const subErr = ref('')
const canManage = computed(() => auth.can('inventory.manage'))

async function load(p = 1) { pg.value = p; page.value = await api.get('/app/inventory/items', { q: filt.q, category_id: filt.category_id, low_stock: filt.low_stock ? 1 : '', page: p }) }
async function loadMeta() { meta.value = await api.get('/app/inventory/items/meta') }
onMounted(async () => { await loadMeta(); await load() })
async function remove(i: any) { if (!confirm(tt(`حذف الصنف ${i.name}؟`,`Delete item ${i.name}?`))) return; try { const r = await api.del(`/app/inventory/items/${i.id}`); if (r.deactivated) alert(tt('الصنف له حركات، تم تعطيله بدل حذفه.','Item has movements; deactivated instead.')); await load(pg.value) } catch (e: any) { alert(e.message) } }
async function addSub() {
  subErr.value = ''
  try {
    if (sub.value === 'cat') await api.post('/app/inventory/categories', { name: subName.value })
    else await api.post('/app/inventory/units', { name: subName.value, symbol: subSym.value })
    subName.value = ''; subSym.value = ''; sub.value = null; await loadMeta()
  } catch (e: any) { subErr.value = e.message }
}
const stockValue = computed(() => (page.value?.data || []).reduce((s: number, i: any) => s + (+i.stock || 0) * (+i.cost_price || 0), 0))
const lowCount = computed(() => (page.value?.data || []).filter((i: any) => i.track_stock && +(i.stock || 0) <= +i.min_stock).length)
</script>

<template>
  <div>
    <div class="breadcrumb"><NuxtLink to="/inventory">{{ tt('المخزون','Inventory') }}</NuxtLink> › <b>{{ tt('الأصناف','Items') }}</b></div>

    <div class="grid grid-4" style="margin-bottom:16px">
      <div class="card stat"><div class="ic" style="background:var(--info)"><i class="ti ti-box" /></div><div><b>{{ page?.total ?? 0 }}</b><small>{{ tt('صنف مسجّل','items') }}</small></div></div>
      <div class="card stat"><div class="ic" style="background:var(--ok)"><i class="ti ti-category" /></div><div><b>{{ meta.categories.length }}</b><small>{{ tt('تصنيف','categories') }} · {{ meta.units.length }} {{ tt('وحدة قياس','units') }}</small></div></div>
      <div class="card stat"><div class="ic" style="background:var(--warn)"><i class="ti ti-coins" /></div><div><b>{{ f.money(stockValue) }}</b><small>{{ tt('قيمة مخزون الصفحة (بالتكلفة)','Page stock value (cost)') }}</small></div></div>
      <div class="card stat"><div class="ic" style="background:var(--primary)"><i class="ti ti-alert-triangle" /></div><div><b>{{ lowCount }}</b><small>{{ tt('تحت الحد الأدنى (في الصفحة)','Below minimum (page)') }}</small></div></div>
    </div>

    <div class="card">
      <div class="row between" style="margin-bottom:14px">
        <div class="row">
          <input v-model="filt.q" :placeholder="tt('اسم / SKU / باركود','Name / SKU / barcode')" style="padding:8px 12px;border:1px solid var(--line);border-radius:10px;min-width:200px" @keyup.enter="load()" />
          <UiSelect v-model="filt.category_id" style="min-width:150px" @change="load()"><option value="">{{ tt('كل التصنيفات','All categories') }}</option><option v-for="c in meta.categories" :key="c.id" :value="c.id">{{ c.name }}</option></UiSelect>
          <label class="switch"><input v-model="filt.low_stock" type="checkbox" @change="load()" /><span /> {{ tt('تحت الحد الأدنى','Below minimum') }}</label>
          <button class="btn ghost" @click="load()"><i class="ti ti-search" /></button>
        </div>
        <div v-if="canManage" class="row">
          <button class="btn ghost" @click="sub = 'cat'"><i class="ti ti-category" /> {{ tt('تصنيف','Category') }}</button>
          <button class="btn ghost" @click="sub = 'unit'"><i class="ti ti-ruler" /> {{ tt('وحدة','Unit') }}</button>
          <NuxtLink class="btn" to="/inventory/items/new"><i class="ti ti-plus" /> {{ tt('صنف جديد','New item') }}</NuxtLink>
        </div>
      </div>
      <table class="table">
        <thead><tr><th>#</th><th>{{ tt('الصنف','Item') }}</th><th>{{ tt('SKU / باركود','SKU / barcode') }}</th><th>{{ tt('التصنيف','Category') }}</th><th>{{ tt('العلامة','Brand') }}</th><th>{{ tt('التكلفة','Cost') }}</th><th>{{ tt('سعر البيع','Sale price') }}</th><th>{{ tt('المخزون','Stock') }}</th><th>{{ tt('الحالة','Status') }}</th><th></th></tr></thead>
        <tbody>
          <tr v-for="(i, idx) in page?.data" :key="i.id">
            <td class="muted">{{ (page.current_page - 1) * page.per_page + idx + 1 }}</td>
            <td><NuxtLink :to="`/inventory/items/${i.id}`" style="color:var(--info);font-weight:700">{{ i.name }}</NuxtLink><br /><small class="muted">{{ i.name_en }}</small></td>
            <td dir="ltr" style="text-align:right"><small>{{ i.sku || '—' }}<br />{{ i.barcode || '' }}</small></td>
            <td>{{ i.category?.name || '—' }}</td>
            <td class="muted">{{ i.brand || '—' }}</td>
            <td class="num">{{ f.money(i.cost_price) }}</td>
            <td class="num"><b>{{ f.money(i.sale_price) }}</b></td>
            <td class="num"><span v-if="i.track_stock" :style="+(i.stock || 0) <= +i.min_stock ? 'color:var(--primary);font-weight:700' : ''">{{ f.qty(i.stock) }} {{ i.unit?.symbol || '' }}</span><span v-else class="muted">{{ tt('خدمة','Service') }}</span></td>
            <td><span class="badge" :class="i.is_active ? 'ok' : 'muted'">{{ i.is_active ? tt('نشط','Active') : tt('موقوف','Inactive') }}</span></td>
            <td class="row" style="gap:6px">
              <NuxtLink class="btn ghost sm icon" :to="`/inventory/items/${i.id}`"><i class="ti ti-eye" /></NuxtLink>
              <template v-if="canManage"><NuxtLink class="btn ghost sm icon" :to="`/inventory/items/${i.id}?edit=1`"><i class="ti ti-pencil" /></NuxtLink><button class="btn ghost sm icon" style="color:var(--primary)" @click="remove(i)"><i class="ti ti-trash" /></button></template>
            </td>
          </tr>
        </tbody>
      </table>
      <p v-if="page && !page.data.length" class="empty">{{ tt('لا توجد أصناف.','No items.') }}</p>
      <div v-if="page && page.last_page > 1" class="row between mt">
        <span class="muted" style="font-size:12px">{{ tt('صفحة','Page') }} {{ page.current_page }} {{ tt('من','of') }} {{ page.last_page }} · {{ page.total }}</span>
        <div class="row"><button class="btn ghost sm" :disabled="pg <= 1" @click="load(pg - 1)">{{ tt('السابق','Prev') }}</button><button class="btn ghost sm" :disabled="pg >= page.last_page" @click="load(pg + 1)">{{ tt('التالي','Next') }}</button></div>
      </div>
    </div>

    <!-- Small essential helpers: category / unit quick-add -->
    <UiModal v-if="sub" :title="sub === 'cat' ? tt('تصنيف جديد','New category') : tt('وحدة قياس جديدة','New unit')" @close="sub = null">
      <div v-if="subErr" class="alert bad">{{ subErr }}</div>
      <div class="field"><label>{{ tt('الاسم','Name') }}</label><input v-model="subName" @keyup.enter="addSub" /></div>
      <div v-if="sub === 'unit'" class="field"><label>{{ tt('الرمز (مثل: قطعة، كجم)','Symbol (e.g. pcs, kg)') }}</label><input v-model="subSym" /></div>
      <div class="chips mt"><span v-for="c in (sub === 'cat' ? meta.categories : meta.units)" :key="c.id" class="chip">{{ c.name }}</span></div>
      <template #footer><button class="btn ghost" @click="sub = null">{{ tt('إغلاق','Close') }}</button><button class="btn" :disabled="!subName" @click="addSub">{{ tt('إضافة','Add') }}</button></template>
    </UiModal>
  </div>
</template>
