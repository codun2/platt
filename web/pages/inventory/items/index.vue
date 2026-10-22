<script setup lang="ts">
definePageMeta({ title: 'الأصناف' })
const api = useApi(); const auth = useAuth(); const f = useFmt()
const page = ref<any>(null); const meta = ref<any>({ categories: [], units: [], warehouses: [] }); const pg = ref(1)
const filt = reactive({ q: '', category_id: '', low_stock: false })
const sub = ref<'cat' | 'unit' | null>(null); const subName = ref(''); const subSym = ref(''); const subErr = ref('')
const canManage = computed(() => auth.can('inventory.manage'))

async function load(p = 1) { pg.value = p; page.value = await api.get('/app/inventory/items', { q: filt.q, category_id: filt.category_id, low_stock: filt.low_stock ? 1 : '', page: p }) }
async function loadMeta() { meta.value = await api.get('/app/inventory/items/meta') }
onMounted(async () => { await loadMeta(); await load() })
async function remove(i: any) { if (!confirm(`حذف الصنف ${i.name}؟`)) return; try { const r = await api.del(`/app/inventory/items/${i.id}`); if (r.deactivated) alert('الصنف له حركات، تم تعطيله بدل حذفه.'); await load(pg.value) } catch (e: any) { alert(e.message) } }
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
    <div class="breadcrumb"><NuxtLink to="/inventory">المخزون</NuxtLink> › <b>الأصناف</b></div>

    <div class="grid grid-4" style="margin-bottom:16px">
      <div class="card stat"><div class="ic" style="background:var(--info)"><i class="ti ti-box" /></div><div><b>{{ page?.total ?? 0 }}</b><small>صنف مسجّل</small></div></div>
      <div class="card stat"><div class="ic" style="background:var(--ok)"><i class="ti ti-category" /></div><div><b>{{ meta.categories.length }}</b><small>تصنيف · {{ meta.units.length }} وحدة قياس</small></div></div>
      <div class="card stat"><div class="ic" style="background:var(--warn)"><i class="ti ti-coins" /></div><div><b>{{ f.money(stockValue) }}</b><small>قيمة مخزون الصفحة (بالتكلفة)</small></div></div>
      <div class="card stat"><div class="ic" style="background:var(--primary)"><i class="ti ti-alert-triangle" /></div><div><b>{{ lowCount }}</b><small>تحت الحد الأدنى (في الصفحة)</small></div></div>
    </div>

    <div class="card">
      <div class="row between" style="margin-bottom:14px">
        <div class="row">
          <input v-model="filt.q" placeholder="اسم / SKU / باركود" style="padding:8px 12px;border:1px solid var(--line);border-radius:10px;min-width:200px" @keyup.enter="load()" />
          <UiSelect v-model="filt.category_id" style="min-width:150px" @change="load()"><option value="">كل التصنيفات</option><option v-for="c in meta.categories" :key="c.id" :value="c.id">{{ c.name }}</option></UiSelect>
          <label class="switch"><input v-model="filt.low_stock" type="checkbox" @change="load()" /><span /> تحت الحد الأدنى</label>
          <button class="btn ghost" @click="load()"><i class="ti ti-search" /></button>
        </div>
        <div v-if="canManage" class="row">
          <button class="btn ghost" @click="sub = 'cat'"><i class="ti ti-category" /> تصنيف</button>
          <button class="btn ghost" @click="sub = 'unit'"><i class="ti ti-ruler" /> وحدة</button>
          <NuxtLink class="btn" to="/inventory/items/new"><i class="ti ti-plus" /> صنف جديد</NuxtLink>
        </div>
      </div>
      <table class="table">
        <thead><tr><th>#</th><th>الصنف</th><th>SKU / باركود</th><th>التصنيف</th><th>العلامة</th><th>التكلفة</th><th>سعر البيع</th><th>المخزون</th><th>الحالة</th><th></th></tr></thead>
        <tbody>
          <tr v-for="(i, idx) in page?.data" :key="i.id">
            <td class="muted">{{ (page.current_page - 1) * page.per_page + idx + 1 }}</td>
            <td><NuxtLink :to="`/inventory/items/${i.id}`" style="color:var(--info);font-weight:700">{{ i.name }}</NuxtLink><br /><small class="muted">{{ i.name_en }}</small></td>
            <td dir="ltr" style="text-align:right"><small>{{ i.sku || '—' }}<br />{{ i.barcode || '' }}</small></td>
            <td>{{ i.category?.name || '—' }}</td>
            <td class="muted">{{ i.brand || '—' }}</td>
            <td class="num">{{ f.money(i.cost_price) }}</td>
            <td class="num"><b>{{ f.money(i.sale_price) }}</b></td>
            <td class="num"><span v-if="i.track_stock" :style="+(i.stock || 0) <= +i.min_stock ? 'color:var(--primary);font-weight:700' : ''">{{ f.qty(i.stock) }} {{ i.unit?.symbol || '' }}</span><span v-else class="muted">خدمة</span></td>
            <td><span class="badge" :class="i.is_active ? 'ok' : 'muted'">{{ i.is_active ? 'نشط' : 'موقوف' }}</span></td>
            <td class="row" style="gap:6px">
              <NuxtLink class="btn ghost sm icon" :to="`/inventory/items/${i.id}`"><i class="ti ti-eye" /></NuxtLink>
              <template v-if="canManage"><NuxtLink class="btn ghost sm icon" :to="`/inventory/items/${i.id}?edit=1`"><i class="ti ti-pencil" /></NuxtLink><button class="btn ghost sm icon" style="color:var(--primary)" @click="remove(i)"><i class="ti ti-trash" /></button></template>
            </td>
          </tr>
        </tbody>
      </table>
      <p v-if="page && !page.data.length" class="empty">لا توجد أصناف.</p>
      <div v-if="page && page.last_page > 1" class="row between mt">
        <span class="muted" style="font-size:12px">صفحة {{ page.current_page }} من {{ page.last_page }} · {{ page.total }} صنف</span>
        <div class="row"><button class="btn ghost sm" :disabled="pg <= 1" @click="load(pg - 1)">السابق</button><button class="btn ghost sm" :disabled="pg >= page.last_page" @click="load(pg + 1)">التالي</button></div>
      </div>
    </div>

    <!-- Small essential helpers: category / unit quick-add -->
    <UiModal v-if="sub" :title="sub === 'cat' ? 'تصنيف جديد' : 'وحدة قياس جديدة'" @close="sub = null">
      <div v-if="subErr" class="alert bad">{{ subErr }}</div>
      <div class="field"><label>الاسم</label><input v-model="subName" @keyup.enter="addSub" /></div>
      <div v-if="sub === 'unit'" class="field"><label>الرمز (مثل: قطعة، كجم)</label><input v-model="subSym" /></div>
      <div class="chips mt"><span v-for="c in (sub === 'cat' ? meta.categories : meta.units)" :key="c.id" class="chip">{{ c.name }}</span></div>
      <template #footer><button class="btn ghost" @click="sub = null">إغلاق</button><button class="btn" :disabled="!subName" @click="addSub">إضافة</button></template>
    </UiModal>
  </div>
</template>
