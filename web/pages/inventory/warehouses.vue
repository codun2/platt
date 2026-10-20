<script setup lang="ts">
definePageMeta({ title: 'المستودعات والموردون' })
const api = useApi(); const auth = useAuth(); const f = useFmt()
const tab = ref<'wh' | 'sup' | 'adjust'>('wh')
const warehouses = ref<any[]>([]); const suppliers = ref<any[]>([])
const modal = ref<'wh' | 'sup' | null>(null); const editing = ref<any>(null); const err = ref('')
const form = reactive<any>({})
const canManage = computed(() => auth.can('inventory.manage'))

// stock adjust / transfer
const items = ref<any[]>([]); const iq = ref('')
const adj = reactive({ item_id: '', warehouse_id: '', qty: 0, note: '' })
const trf = reactive({ item_id: '', from_warehouse_id: '', to_warehouse_id: '', qty: 0, note: '' })
const msg = ref('')

async function load() { warehouses.value = await api.get('/app/inventory/warehouses'); suppliers.value = await api.get('/app/inventory/suppliers') }
onMounted(load)
async function searchItems() { items.value = (await api.get('/app/inventory/items/lookup', { q: iq.value })).items }

function open(kind: 'wh' | 'sup', row: any = null) {
  editing.value = row; err.value = ''
  Object.keys(form).forEach(k => delete form[k])
  Object.assign(form, kind === 'wh' ? { name: '', location: '', is_default: false, is_active: true, ...(row || {}) } : { name: '', phone: '', email: '', tax_number: '', address: '', notes: '', is_active: true, ...(row || {}) })
  for (const k of Object.keys(form)) if (form[k] === null) form[k] = ''
  modal.value = kind
}
async function save() {
  err.value = ''; const kind = modal.value === 'wh' ? 'warehouses' : 'suppliers'
  try { editing.value ? await api.patch(`/app/inventory/${kind}/${editing.value.id}`, form) : await api.post(`/app/inventory/${kind}`, form); modal.value = null; await load() }
  catch (e: any) { err.value = e.message }
}
async function remove(kind: string, r: any) { if (!confirm(`حذف ${r.name}؟`)) return; try { await api.del(`/app/inventory/${kind}/${r.id}`); await load() } catch (e: any) { alert(e.message) } }
async function doAdjust() { msg.value = ''; try { await api.post('/app/inventory/stock/adjust', adj); msg.value = 'تم تحديث الرصيد'; adj.qty = 0 } catch (e: any) { alert(e.message) } }
async function doTransfer() { msg.value = ''; try { await api.post('/app/inventory/stock/transfer', trf); msg.value = 'تم التحويل'; trf.qty = 0 } catch (e: any) { alert(e.message) } }
</script>

<template>
  <div>
    <div class="breadcrumb"><NuxtLink to="/inventory">المخزون</NuxtLink> › <b>المستودعات والموردون</b></div>
    <div class="card">
      <div class="tabs"><button :class="{ on: tab === 'wh' }" @click="tab = 'wh'">المستودعات</button><button :class="{ on: tab === 'sup' }" @click="tab = 'sup'">الموردون</button><button v-if="canManage" :class="{ on: tab === 'adjust' }" @click="tab = 'adjust'">جرد / تحويل</button></div>

      <div v-if="tab === 'wh'">
        <div class="row end" style="margin-bottom:12px"><button v-if="canManage" class="btn" @click="open('wh')"><i class="ti ti-plus" /> مستودع</button></div>
        <table class="table"><thead><tr><th>الاسم</th><th>الموقع</th><th>أصناف</th><th>افتراضي</th><th>الحالة</th><th></th></tr></thead>
          <tbody><tr v-for="w in warehouses" :key="w.id"><td><b>{{ w.name }}</b></td><td>{{ w.location || '—' }}</td><td>{{ w.stock_levels_count }}</td><td><span v-if="w.is_default" class="badge info">افتراضي</span></td><td><span class="badge" :class="w.is_active ? 'ok' : 'muted'">{{ w.is_active ? 'نشط' : 'موقوف' }}</span></td>
            <td v-if="canManage" class="row" style="gap:6px"><button class="btn ghost sm icon" @click="open('wh', w)"><i class="ti ti-pencil" /></button><button v-if="!w.is_default" class="btn ghost sm icon" style="color:var(--primary)" @click="remove('warehouses', w)"><i class="ti ti-trash" /></button></td></tr></tbody></table>
      </div>

      <div v-if="tab === 'sup'">
        <div class="row end" style="margin-bottom:12px"><button v-if="canManage" class="btn" @click="open('sup')"><i class="ti ti-plus" /> مورد</button></div>
        <table class="table"><thead><tr><th>الاسم</th><th>الهاتف</th><th>البريد</th><th>فواتير</th><th>الحالة</th><th></th></tr></thead>
          <tbody><tr v-for="s in suppliers" :key="s.id"><td><b>{{ s.name }}</b></td><td dir="ltr" style="text-align:right">{{ s.phone || '—' }}</td><td dir="ltr" style="text-align:right">{{ s.email || '—' }}</td><td>{{ s.purchases_count }}</td><td><span class="badge" :class="s.is_active ? 'ok' : 'muted'">{{ s.is_active ? 'نشط' : 'موقوف' }}</span></td>
            <td v-if="canManage" class="row" style="gap:6px"><button class="btn ghost sm icon" @click="open('sup', s)"><i class="ti ti-pencil" /></button><button class="btn ghost sm icon" style="color:var(--primary)" @click="remove('suppliers', s)"><i class="ti ti-trash" /></button></td></tr></tbody></table>
        <p v-if="!suppliers.length" class="empty">لا يوجد موردون.</p>
      </div>

      <div v-if="tab === 'adjust'" class="grid grid-2">
        <div>
          <div v-if="msg" class="alert ok">{{ msg }}</div>
          <div class="field"><label>ابحث عن صنف</label><div class="row"><input v-model="iq" placeholder="اسم / SKU / باركود" @keyup.enter="searchItems" /><button class="btn ghost" @click="searchItems"><i class="ti ti-search" /></button></div></div>
          <div class="chips" style="max-height:200px;overflow:auto"><span v-for="i in items" :key="i.id" class="chip" :class="{ on: adj.item_id === i.id }" @click="adj.item_id = i.id; trf.item_id = i.id">{{ i.name }} <small class="muted">({{ f.qty(i.stock) }})</small></span></div>
        </div>
        <div>
          <div class="card-title"><i class="ti ti-clipboard-check" /> جرد (تعيين الكمية الفعلية)</div>
          <div class="field"><label>المستودع</label><select v-model="adj.warehouse_id"><option v-for="w in warehouses" :key="w.id" :value="w.id">{{ w.name }}</option></select></div>
          <div class="field"><label>الكمية الفعلية</label><input v-model.number="adj.qty" type="number" step="0.001" dir="ltr" /></div>
          <div class="field"><label>ملاحظة</label><input v-model="adj.note" /></div>
          <button class="btn" :disabled="!adj.item_id || !adj.warehouse_id" @click="doAdjust">حفظ الجرد</button>
          <div class="card-title mt"><i class="ti ti-arrows-exchange" /> تحويل بين مستودعين</div>
          <div class="grid grid-2">
            <div class="field"><label>من</label><select v-model="trf.from_warehouse_id"><option v-for="w in warehouses" :key="w.id" :value="w.id">{{ w.name }}</option></select></div>
            <div class="field"><label>إلى</label><select v-model="trf.to_warehouse_id"><option v-for="w in warehouses" :key="w.id" :value="w.id">{{ w.name }}</option></select></div>
          </div>
          <div class="field"><label>الكمية</label><input v-model.number="trf.qty" type="number" step="0.001" dir="ltr" /></div>
          <button class="btn info" :disabled="!trf.item_id || !trf.from_warehouse_id || !trf.to_warehouse_id" @click="doTransfer">تحويل</button>
        </div>
      </div>
    </div>

    <UiModal v-if="modal" :title="editing ? 'تعديل' : (modal === 'wh' ? 'مستودع جديد' : 'مورد جديد')" @close="modal = null">
      <div v-if="err" class="alert bad">{{ err }}</div>
      <template v-if="modal === 'wh'">
        <div class="field"><label>الاسم</label><input v-model="form.name" /></div>
        <div class="field"><label>الموقع</label><input v-model="form.location" /></div>
        <div class="row"><label class="switch"><input v-model="form.is_default" type="checkbox" /><span /> افتراضي</label><label class="switch"><input v-model="form.is_active" type="checkbox" /><span /> نشط</label></div>
      </template>
      <template v-else>
        <div class="grid grid-2">
          <div class="field"><label>الاسم</label><input v-model="form.name" /></div>
          <div class="field"><label>الهاتف</label><input v-model="form.phone" dir="ltr" /></div>
          <div class="field"><label>البريد</label><input v-model="form.email" dir="ltr" /></div>
          <div class="field"><label>الرقم الضريبي</label><input v-model="form.tax_number" dir="ltr" /></div>
        </div>
        <div class="field"><label>العنوان</label><input v-model="form.address" /></div>
        <label class="switch"><input v-model="form.is_active" type="checkbox" /><span /> نشط</label>
      </template>
      <template #footer><button class="btn ghost" @click="modal = null">إلغاء</button><button class="btn" @click="save">حفظ</button></template>
    </UiModal>
  </div>
</template>
