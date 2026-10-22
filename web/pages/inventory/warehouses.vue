<script setup lang="ts">
definePageMeta({ title: 'المستودعات' })
const api = useApi(); const auth = useAuth(); const f = useFmt()
const tab = ref<'wh' | 'adjust'>('wh')
const warehouses = ref<any[]>([])
const modal = ref(false); const editing = ref<any>(null); const err = ref('')
const form = reactive<any>({ name: '', location: '', is_default: false, is_active: true })
const canManage = computed(() => auth.can('inventory.manage'))

// stock adjust / transfer
const items = ref<any[]>([]); const iq = ref('')
const adj = reactive({ item_id: '', warehouse_id: '', qty: 0, note: '' })
const trf = reactive({ item_id: '', from_warehouse_id: '', to_warehouse_id: '', qty: 0, note: '' })
const msg = ref('')

async function load() { warehouses.value = await api.get('/app/inventory/warehouses') }
onMounted(load)
async function searchItems() { items.value = (await api.get('/app/inventory/items/lookup', { q: iq.value })).items }

function open(row: any = null) { editing.value = row; err.value = ''; Object.assign(form, { name: '', location: '', is_default: false, is_active: true, ...(row || {}) }); form.location = form.location || ''; modal.value = true }
async function save() {
  err.value = ''
  try { editing.value ? await api.patch(`/app/inventory/warehouses/${editing.value.id}`, form) : await api.post('/app/inventory/warehouses', form); modal.value = false; await load() }
  catch (e: any) { err.value = e.message }
}
async function remove(r: any) { if (!confirm(`حذف ${r.name}؟`)) return; try { await api.del(`/app/inventory/warehouses/${r.id}`); await load() } catch (e: any) { alert(e.message) } }
async function doAdjust() { msg.value = ''; try { await api.post('/app/inventory/stock/adjust', adj); msg.value = 'تم تحديث الرصيد'; adj.qty = 0 } catch (e: any) { alert(e.message) } }
async function doTransfer() { msg.value = ''; try { await api.post('/app/inventory/stock/transfer', trf); msg.value = 'تم التحويل'; trf.qty = 0 } catch (e: any) { alert(e.message) } }
</script>

<template>
  <div>
    <div class="breadcrumb"><NuxtLink to="/inventory">المخزون</NuxtLink> › <b>المستودعات</b></div>
    <div class="card">
      <div class="row between" style="margin-bottom:4px">
        <div class="tabs" style="margin-bottom:0;border:none"><button :class="{ on: tab === 'wh' }" @click="tab = 'wh'">المستودعات</button><button v-if="canManage" :class="{ on: tab === 'adjust' }" @click="tab = 'adjust'">جرد / تحويل</button></div>
        <div class="row"><NuxtLink class="btn ghost sm" to="/inventory/suppliers"><i class="ti ti-truck" /> الموردون</NuxtLink><button v-if="canManage && tab === 'wh'" class="btn sm" @click="open()"><i class="ti ti-plus" /> مستودع</button></div>
      </div>
      <hr style="border:none;border-top:1px solid var(--line);margin:0 0 16px">

      <div v-if="tab === 'wh'">
        <table class="table"><thead><tr><th>الاسم</th><th>الموقع</th><th>أصناف</th><th>افتراضي</th><th>الحالة</th><th></th></tr></thead>
          <tbody><tr v-for="w in warehouses" :key="w.id"><td><b>{{ w.name }}</b></td><td>{{ w.location || '—' }}</td><td>{{ w.stock_levels_count }}</td><td><span v-if="w.is_default" class="badge info">افتراضي</span></td><td><span class="badge" :class="w.is_active ? 'ok' : 'muted'">{{ w.is_active ? 'نشط' : 'موقوف' }}</span></td>
            <td v-if="canManage" class="row" style="gap:6px"><button class="btn ghost sm icon" @click="open(w)"><i class="ti ti-pencil" /></button><button v-if="!w.is_default" class="btn ghost sm icon" style="color:var(--primary)" @click="remove(w)"><i class="ti ti-trash" /></button></td></tr></tbody></table>
        <p v-if="!warehouses.length" class="empty">لا توجد مستودعات.</p>
      </div>

      <div v-if="tab === 'adjust'" class="grid grid-2">
        <div>
          <div v-if="msg" class="alert ok">{{ msg }}</div>
          <div class="field"><label>ابحث عن صنف</label><div class="row"><input v-model="iq" placeholder="اسم / SKU / باركود" @keyup.enter="searchItems" /><button class="btn ghost" @click="searchItems"><i class="ti ti-search" /></button></div></div>
          <div class="chips" style="max-height:200px;overflow:auto"><span v-for="i in items" :key="i.id" class="chip" :class="{ on: adj.item_id === i.id }" @click="adj.item_id = i.id; trf.item_id = i.id">{{ i.name }} <small class="muted">({{ f.qty(i.stock) }})</small></span></div>
        </div>
        <div>
          <div class="card-title"><i class="ti ti-clipboard-check" /> جرد (تعيين الكمية الفعلية)</div>
          <div class="field"><label>المستودع</label><UiSelect v-model="adj.warehouse_id"><option value="">—</option><option v-for="w in warehouses" :key="w.id" :value="w.id">{{ w.name }}</option></UiSelect></div>
          <div class="field"><label>الكمية الفعلية</label><input v-model.number="adj.qty" type="number" step="0.001" dir="ltr" /></div>
          <div class="field"><label>ملاحظة</label><input v-model="adj.note" /></div>
          <button class="btn" :disabled="!adj.item_id || !adj.warehouse_id" @click="doAdjust">حفظ الجرد</button>
          <div class="card-title mt"><i class="ti ti-arrows-exchange" /> تحويل بين مستودعين</div>
          <div class="grid grid-2">
            <div class="field"><label>من</label><UiSelect v-model="trf.from_warehouse_id"><option value="">—</option><option v-for="w in warehouses" :key="w.id" :value="w.id">{{ w.name }}</option></UiSelect></div>
            <div class="field"><label>إلى</label><UiSelect v-model="trf.to_warehouse_id"><option value="">—</option><option v-for="w in warehouses" :key="w.id" :value="w.id">{{ w.name }}</option></UiSelect></div>
          </div>
          <div class="field"><label>الكمية</label><input v-model.number="trf.qty" type="number" step="0.001" dir="ltr" /></div>
          <button class="btn info" :disabled="!trf.item_id || !trf.from_warehouse_id || !trf.to_warehouse_id" @click="doTransfer">تحويل</button>
        </div>
      </div>
    </div>

    <UiModal v-if="modal" :title="editing ? 'تعديل مستودع' : 'مستودع جديد'" @close="modal = false">
      <div v-if="err" class="alert bad">{{ err }}</div>
      <div class="field"><label>الاسم</label><input v-model="form.name" /></div>
      <div class="field"><label>الموقع</label><input v-model="form.location" /></div>
      <div class="row"><label class="switch"><input v-model="form.is_default" type="checkbox" /><span /> افتراضي</label><label class="switch"><input v-model="form.is_active" type="checkbox" /><span /> نشط</label></div>
      <template #footer><button class="btn ghost" @click="modal = false">إلغاء</button><button class="btn" @click="save">حفظ</button></template>
    </UiModal>
  </div>
</template>
