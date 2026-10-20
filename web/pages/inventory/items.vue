<script setup lang="ts">
definePageMeta({ title: 'الأصناف' })
const api = useApi(); const auth = useAuth(); const f = useFmt()
const page = ref<any>(null); const meta = ref<any>({ categories: [], units: [], warehouses: [] })
const q = ref(''); const cat = ref(''); const lowOnly = ref(false)
const modal = ref(false); const editing = ref<any>(null); const err = ref('')
const blank = () => ({ name: '', name_en: '', sku: '', barcode: '', category_id: '', unit_id: '', cost_price: 0, sale_price: 0, track_stock: true, min_stock: 0, is_active: true, notes: '', opening_qty: 0 })
const form = reactive<any>(blank())
const detail = ref<any>(null)
const sub = ref<'cat' | 'unit' | null>(null); const subName = ref(''); const subSym = ref('')
const canManage = computed(() => auth.can('inventory.manage'))

async function load() { page.value = await api.get('/app/inventory/items', { q: q.value, category_id: cat.value, low_stock: lowOnly.value ? 1 : '' }) }
async function loadMeta() { meta.value = await api.get('/app/inventory/items/meta') }
onMounted(async () => { await loadMeta(); await load() })

function openNew() { editing.value = null; Object.assign(form, blank()); err.value = ''; modal.value = true }
function openEdit(i: any) { editing.value = i; Object.assign(form, { ...blank(), ...i, category_id: i.category_id || '', unit_id: i.unit_id || '', name_en: i.name_en || '', sku: i.sku || '', barcode: i.barcode || '', notes: i.notes || '' }); err.value = ''; modal.value = true }
async function save() {
  err.value = ''
  const body = { ...form, category_id: form.category_id || null, unit_id: form.unit_id || null }
  try { editing.value ? await api.patch(`/app/inventory/items/${editing.value.id}`, body) : await api.post('/app/inventory/items', body); modal.value = false; await load() }
  catch (e: any) { err.value = e.message }
}
async function remove(i: any) { if (!confirm(`حذف الصنف ${i.name}؟`)) return; try { const r = await api.del(`/app/inventory/items/${i.id}`); if (r.deactivated) alert('الصنف له حركات، تم تعطيله بدل حذفه.'); await load() } catch (e: any) { alert(e.message) } }
async function show(i: any) { detail.value = await api.get(`/app/inventory/items/${i.id}`) }
async function addSub() {
  try {
    if (sub.value === 'cat') await api.post('/app/inventory/categories', { name: subName.value })
    else await api.post('/app/inventory/units', { name: subName.value, symbol: subSym.value })
    subName.value = ''; subSym.value = ''; sub.value = null; await loadMeta()
  } catch (e: any) { alert(e.message) }
}
const typeLabel = (t: string) => ({ opening: 'رصيد افتتاحي', purchase: 'شراء', purchase_cancel: 'إلغاء شراء', sale: 'بيع', sale_cancel: 'إلغاء بيع', adjustment: 'جرد/تسوية', transfer_in: 'تحويل وارد', transfer_out: 'تحويل صادر' } as any)[t] || t
</script>

<template>
  <div>
    <div class="breadcrumb"><NuxtLink to="/inventory">المخزون</NuxtLink> › <b>الأصناف</b></div>
    <div class="card">
      <div class="row between" style="margin-bottom:14px">
        <div class="row">
          <input v-model="q" placeholder="اسم / SKU / باركود" style="padding:8px 12px;border:1px solid var(--line);border-radius:10px" @keyup.enter="load" />
          <select v-model="cat" style="padding:8px 12px;border:1px solid var(--line);border-radius:10px" @change="load"><option value="">كل التصنيفات</option><option v-for="c in meta.categories" :key="c.id" :value="c.id">{{ c.name }}</option></select>
          <label class="switch"><input v-model="lowOnly" type="checkbox" @change="load" /><span /> تحت الحد الأدنى</label>
          <button class="btn ghost" @click="load"><i class="ti ti-search" /></button>
        </div>
        <div v-if="canManage" class="row">
          <button class="btn ghost" @click="sub = 'cat'"><i class="ti ti-category" /> تصنيف</button>
          <button class="btn ghost" @click="sub = 'unit'"><i class="ti ti-ruler" /> وحدة</button>
          <button class="btn" @click="openNew"><i class="ti ti-plus" /> صنف جديد</button>
        </div>
      </div>
      <table class="table">
        <thead><tr><th>#</th><th>الصنف</th><th>SKU / باركود</th><th>التصنيف</th><th>التكلفة</th><th>سعر البيع</th><th>المخزون</th><th>الحالة</th><th>الإجراءات</th></tr></thead>
        <tbody>
          <tr v-for="(i, idx) in page?.data" :key="i.id">
            <td>{{ idx + 1 }}</td>
            <td><a href="#" style="color:var(--info);font-weight:700" @click.prevent="show(i)">{{ i.name }}</a><br /><small class="muted">{{ i.name_en }}</small></td>
            <td dir="ltr" style="text-align:right"><small>{{ i.sku || '—' }}<br />{{ i.barcode || '' }}</small></td>
            <td>{{ i.category?.name || '—' }}</td>
            <td class="num">{{ f.money(i.cost_price) }}</td>
            <td class="num"><b>{{ f.money(i.sale_price) }}</b></td>
            <td class="num"><span v-if="i.track_stock" :style="Number(i.stock || 0) <= Number(i.min_stock) ? 'color:var(--primary);font-weight:700' : ''">{{ f.qty(i.stock) }} {{ i.unit?.symbol || '' }}</span><span v-else class="muted">خدمة</span></td>
            <td><span class="badge" :class="i.is_active ? 'ok' : 'muted'">{{ i.is_active ? 'نشط' : 'موقوف' }}</span></td>
            <td class="row" style="gap:6px">
              <button class="btn ghost sm icon" @click="show(i)"><i class="ti ti-eye" /></button>
              <template v-if="canManage"><button class="btn ghost sm icon" @click="openEdit(i)"><i class="ti ti-pencil" /></button><button class="btn ghost sm icon" style="color:var(--primary)" @click="remove(i)"><i class="ti ti-trash" /></button></template>
            </td>
          </tr>
        </tbody>
      </table>
      <p v-if="page && !page.data.length" class="empty">لا توجد أصناف.</p>
      <p class="muted mt" style="font-size:12px">الإجمالي: {{ page?.total ?? 0 }}</p>
    </div>

    <UiModal v-if="modal" :title="editing ? 'تعديل صنف' : 'صنف جديد'" lg @close="modal = false">
      <div v-if="err" class="alert bad">{{ err }}</div>
      <div class="grid grid-2">
        <div class="field"><label>الاسم</label><input v-model="form.name" /></div>
        <div class="field"><label>الاسم (إنجليزي)</label><input v-model="form.name_en" dir="ltr" /></div>
        <div class="field"><label>SKU</label><input v-model="form.sku" dir="ltr" /></div>
        <div class="field"><label>الباركود</label><input v-model="form.barcode" dir="ltr" /></div>
        <div class="field"><label>التصنيف</label><select v-model="form.category_id"><option value="">—</option><option v-for="c in meta.categories" :key="c.id" :value="c.id">{{ c.name }}</option></select></div>
        <div class="field"><label>الوحدة</label><select v-model="form.unit_id"><option value="">—</option><option v-for="u in meta.units" :key="u.id" :value="u.id">{{ u.name }} ({{ u.symbol }})</option></select></div>
        <div class="field"><label>سعر التكلفة</label><input v-model.number="form.cost_price" type="number" step="0.001" dir="ltr" /></div>
        <div class="field"><label>سعر البيع</label><input v-model.number="form.sale_price" type="number" step="0.001" dir="ltr" /></div>
        <div class="field"><label>الحد الأدنى للمخزون</label><input v-model.number="form.min_stock" type="number" step="0.001" dir="ltr" /></div>
        <div v-if="!editing" class="field"><label>رصيد افتتاحي (المستودع الافتراضي)</label><input v-model.number="form.opening_qty" type="number" step="0.001" dir="ltr" /></div>
      </div>
      <div class="field"><label>ملاحظات</label><input v-model="form.notes" /></div>
      <div class="row"><label class="switch"><input v-model="form.track_stock" type="checkbox" /><span /> يتتبّع المخزون (أطفئه للخدمات)</label><label class="switch"><input v-model="form.is_active" type="checkbox" /><span /> نشط</label></div>
      <template #footer><button class="btn ghost" @click="modal = false">إلغاء</button><button class="btn" @click="save">حفظ</button></template>
    </UiModal>

    <UiModal v-if="sub" :title="sub === 'cat' ? 'تصنيف جديد' : 'وحدة قياس جديدة'" @close="sub = null">
      <div class="field"><label>الاسم</label><input v-model="subName" /></div>
      <div v-if="sub === 'unit'" class="field"><label>الرمز (مثل: قطعة، كجم)</label><input v-model="subSym" /></div>
      <div class="chips mt"><span v-for="c in (sub === 'cat' ? meta.categories : meta.units)" :key="c.id" class="chip">{{ c.name }}</span></div>
      <template #footer><button class="btn ghost" @click="sub = null">إغلاق</button><button class="btn" @click="addSub">إضافة</button></template>
    </UiModal>

    <UiModal v-if="detail" :title="detail.item.name" lg @close="detail = null">
      <div class="row" style="gap:24px;margin-bottom:12px">
        <div><small class="muted">SKU</small><br /><b dir="ltr">{{ detail.item.sku || '—' }}</b></div>
        <div><small class="muted">سعر البيع</small><br /><b>{{ f.money(detail.item.sale_price, true) }}</b></div>
        <div><small class="muted">التكلفة</small><br /><b>{{ f.money(detail.item.cost_price, true) }}</b></div>
        <div v-for="l in detail.levels" :key="l.id"><small class="muted">{{ l.warehouse?.name }}</small><br /><b>{{ f.qty(l.qty) }}</b></div>
      </div>
      <div class="card-title"><i class="ti ti-history" /> آخر الحركات</div>
      <table class="table"><thead><tr><th>التاريخ</th><th>النوع</th><th>المستودع</th><th>الكمية</th><th>الرصيد بعد</th><th>بواسطة</th></tr></thead>
        <tbody><tr v-for="m in detail.movements" :key="m.id"><td>{{ f.dt(m.created_at) }}</td><td>{{ typeLabel(m.type) }}</td><td>{{ m.warehouse?.name }}</td><td class="num" :style="Number(m.qty) < 0 ? 'color:var(--primary)' : 'color:var(--ok)'">{{ Number(m.qty) > 0 ? '+' : '' }}{{ f.qty(m.qty) }}</td><td class="num">{{ f.qty(m.balance_after) }}</td><td>{{ m.user?.name || '—' }}</td></tr></tbody></table>
      <p v-if="!detail.movements.length" class="empty">لا توجد حركات.</p>
    </UiModal>
  </div>
</template>
