<script setup lang="ts">
definePageMeta({ title: 'ملف الصنف', titleEn: 'Item Profile' })
const api = useApi(); const auth = useAuth(); const f = useFmt(); const { tt } = useI18n()
const route = useRoute(); const id = Number(route.params.id)
const d = ref<any>(null); const meta = ref<any>({ categories: [], units: [], warehouses: [] }); const suppliers = ref<any[]>([])
const tab = ref<'profile' | 'stock' | 'movements' | 'purchases'>('profile')
const editing = ref(route.query.edit === '1'); const err = ref(''); const msg = ref(''); const saving = ref(false); const notFound = ref(false)
const form = reactive<any>(blankItem())
const item = computed(() => d.value?.item)
const canManage = computed(() => auth.can('inventory.manage'))
const stockValue = computed(() => (+item.value?.stock || 0) * (+item.value?.cost_price || 0))
const margin = computed(() => { const c = +item.value?.cost_price || 0, s = +item.value?.sale_price || 0; return c > 0 ? Math.round((s - c) / c * 1000) / 10 : null })

async function load() {
  try { d.value = await api.get(`/app/inventory/items/${id}`) } catch (e: any) { if (e.status === 404) { notFound.value = true; return } throw e }
  Object.assign(form, itemToForm(d.value.item))
}
onMounted(async () => { await load(); meta.value = await api.get('/app/inventory/items/meta'); suppliers.value = await api.get('/app/inventory/suppliers') })
async function save() {
  err.value = ''; msg.value = ''; saving.value = true
  try { await api.patch(`/app/inventory/items/${id}`, itemPayload(form)); msg.value = tt('تم حفظ التعديلات','Changes saved'); editing.value = false; await load() }
  catch (e: any) { err.value = e.message } finally { saving.value = false; window.scrollTo({ top: 0, behavior: 'smooth' }) }
}
async function remove() { if (!await uiConfirm(tt(`حذف الصنف ${item.value.name}؟`,`Delete item ${item.value.name}?`))) return; try { const r = await api.del(`/app/inventory/items/${id}`); if (r.deactivated) { msg.value = tt('الصنف له حركات، تم تعطيله بدل حذفه.','Item has movements; deactivated instead.'); await load() } else navigateTo('/inventory/items') } catch (e: any) { err.value = e.message } }
</script>

<template>
  <div v-if="item">
    <div class="breadcrumb"><NuxtLink to="/inventory/items">{{ tt('الأصناف','Items') }}</NuxtLink> › <b>{{ item.name }}</b></div>
    <div v-if="msg" class="alert ok">{{ msg }}</div>
    <div v-if="err" class="alert bad">{{ err }}</div>

    <div v-if="editing" class="card">
      <ItemForm :form="form" :meta="meta" :suppliers="suppliers" />
      <div class="row" style="gap:10px;margin-top:18px;border-top:1px solid var(--line);padding-top:16px">
        <button class="btn" :disabled="saving" @click="save"><i class="ti ti-check" /> {{ tt('حفظ التعديلات','Save changes') }}</button>
        <button class="btn ghost" :disabled="saving" @click="editing = false; load()">{{ tt('إلغاء','Cancel') }}</button>
      </div>
    </div>

    <template v-else>
      <div class="grid grid-4" style="margin-bottom:16px">
        <div class="card stat"><div class="ic" :style="`background:${item.track_stock && +(item.stock || 0) <= +item.min_stock ? 'var(--primary)' : 'var(--info)'}`"><i class="ti ti-box" /></div><div><b>{{ item.track_stock ? f.qty(item.stock) + ' ' + (item.unit?.symbol || '') : tt('خدمة','Service') }}</b><small>{{ tt('الرصيد الكلي','Total stock') }}<span v-if="item.track_stock"> · {{ tt('الحد الأدنى','min') }} {{ f.qty(item.min_stock) }}</span></small></div></div>
        <div class="card stat"><div class="ic" style="background:var(--ok)"><i class="ti ti-tag" /></div><div><b>{{ f.money(item.sale_price) }}</b><small>{{ tt('سعر البيع','Sale price') }}<span v-if="margin !== null"> · هامش {{ margin }}%</span></small></div></div>
        <div class="card stat"><div class="ic" style="background:var(--warn)"><i class="ti ti-coins" /></div><div><b>{{ f.money(item.cost_price) }}</b><small>{{ tt('سعر التكلفة (آخر شراء)','Cost (last purchase)') }}</small></div></div>
        <div class="card stat"><div class="ic" style="background:var(--primary)"><i class="ti ti-chart-bar" /></div><div><b>{{ f.money(stockValue) }}</b><small>{{ tt('قيمة المخزون بالتكلفة','Stock value at cost') }}</small></div></div>
      </div>

      <div class="card">
        <div class="row between" style="margin-bottom:4px">
          <div class="tabs" style="margin-bottom:0;border:none">
            <button :class="{ on: tab === 'profile' }" @click="tab = 'profile'">{{ tt('الملف','Profile') }}</button>
            <button :class="{ on: tab === 'stock' }" @click="tab = 'stock'">{{ tt('المستودعات','Warehouses') }} ({{ d.levels.length }})</button>
            <button :class="{ on: tab === 'movements' }" @click="tab = 'movements'">{{ tt('الحركات','Movements') }} ({{ d.movements.length }})</button>
            <button :class="{ on: tab === 'purchases' }" @click="tab = 'purchases'">{{ tt('المشتريات','Purchases') }} ({{ d.purchases.length }})</button>
          </div>
          <div class="row" style="gap:8px">
            <span class="badge" :class="item.is_active ? 'ok' : 'muted'">{{ item.is_active ? tt('نشط','Active') : tt('موقوف','Inactive') }}</span>
            <template v-if="canManage"><button class="btn ghost sm" @click="editing = true"><i class="ti ti-pencil" /> {{ tt('تعديل','Edit') }}</button><button class="btn ghost sm" style="color:var(--primary)" @click="remove"><i class="ti ti-trash" /></button></template>
          </div>
        </div>
        <hr style="border:none;border-top:1px solid var(--line);margin:0 0 16px">

        <div v-if="tab === 'profile'" class="grid grid-3">
          <div><small class="muted">{{ tt('الاسم','Name') }}</small><br /><b>{{ item.name }}</b><br /><small class="muted">{{ item.name_en }}</small></div>
          <div><small class="muted">SKU</small><br /><b dir="ltr">{{ item.sku || '—' }}</b></div>
          <div><small class="muted">{{ tt('الباركود','Barcode') }}</small><br /><b dir="ltr">{{ item.barcode || '—' }}</b></div>
          <div><small class="muted">{{ tt('التصنيف','Category') }}</small><br /><b>{{ item.category?.name || '—' }}</b></div>
          <div><small class="muted">{{ tt('وحدة القياس','Unit') }}</small><br /><b>{{ item.unit ? `${item.unit.name} (${item.unit.symbol || ''})` : '—' }}</b></div>
          <div><small class="muted">{{ tt('العلامة / الموديل','Brand / model') }}</small><br /><b>{{ [item.brand, item.model].filter(Boolean).join(' · ') || '—' }}</b></div>
          <div><small class="muted">{{ tt('المورد الافتراضي','Default supplier') }}</small><br /><b v-if="item.supplier"><NuxtLink :to="`/inventory/suppliers/${item.supplier.id}`" style="color:var(--info)">{{ item.supplier.name }}</NuxtLink></b><b v-else>—</b></div>
          <div><small class="muted">{{ tt('الموقع / الرف','Location / shelf') }}</small><br /><b>{{ item.location || '—' }}</b></div>
          <div><small class="muted">{{ tt('الوزن','Weight') }}</small><br /><b>{{ item.weight ? f.qty(item.weight) + tt(' كجم',' kg') : '—' }}</b></div>
          <div><small class="muted">{{ tt('كمية إعادة الطلب','Reorder qty') }}</small><br /><b>{{ item.reorder_qty != null ? f.qty(item.reorder_qty) : '—' }}</b></div>
          <div><small class="muted">{{ tt('الحد الأقصى','Maximum') }}</small><br /><b>{{ item.max_stock != null ? f.qty(item.max_stock) : '—' }}</b></div>
          <div><small class="muted">{{ tt('تتبّع المخزون','Track stock') }}</small><br /><b>{{ item.track_stock ? tt('نعم','Yes') : tt('لا (خدمة)','No (service)') }}</b></div>
          <div v-if="item.description" style="grid-column:1/-1"><small class="muted">{{ tt('الوصف','Description') }}</small><br />{{ item.description }}</div>
          <div v-if="item.notes" style="grid-column:1/-1;background:var(--bg);padding:10px;border-radius:10px"><small class="muted"><i class="ti ti-lock" /> {{ tt('ملاحظات داخلية','Internal notes') }}</small><br />{{ item.notes }}</div>
        </div>

        <div v-else-if="tab === 'stock'">
          <table class="table"><thead><tr><th>{{ tt('المستودع','Warehouse') }}</th><th>{{ tt('الكمية','Qty') }}</th><th>{{ tt('القيمة بالتكلفة','Value at cost') }}</th></tr></thead>
            <tbody><tr v-for="l in d.levels" :key="l.id"><td>{{ l.warehouse?.name }}</td><td class="num"><b>{{ f.qty(l.qty) }}</b></td><td class="num">{{ f.money((+l.qty || 0) * (+item.cost_price || 0)) }}</td></tr></tbody></table>
          <p v-if="!d.levels.length" class="empty">{{ tt('لا يوجد رصيد في أي مستودع.','No stock in any warehouse.') }}</p>
          <p class="muted mt" style="font-size:12px">{{ tt('للجرد أو التحويل بين المستودعات:','Stock-take & transfers:') }} <NuxtLink to="/inventory/warehouses" style="color:var(--info)">{{ tt('صفحة المستودعات','Warehouses page') }}</NuxtLink>.</p>
        </div>

        <div v-else-if="tab === 'movements'">
          <table class="table"><thead><tr><th>{{ tt('التاريخ','Date') }}</th><th>{{ tt('النوع','Type') }}</th><th>{{ tt('المستودع','Warehouse') }}</th><th>{{ tt('الكمية','Qty') }}</th><th>{{ tt('الرصيد بعد','Balance after') }}</th><th>{{ tt('التكلفة','Cost') }}</th><th>{{ tt('ملاحظة','Note') }}</th><th>{{ tt('بواسطة','By') }}</th></tr></thead>
            <tbody><tr v-for="m in d.movements" :key="m.id"><td>{{ f.dt(m.created_at) }}</td><td>{{ movementLabel(m.type) }}</td><td>{{ m.warehouse?.name }}</td><td class="num" :style="+m.qty < 0 ? 'color:var(--primary)' : 'color:var(--ok)'">{{ +m.qty > 0 ? '+' : '' }}{{ f.qty(m.qty) }}</td><td class="num">{{ f.qty(m.balance_after) }}</td><td class="num">{{ m.unit_cost != null ? f.money(m.unit_cost) : '—' }}</td><td class="muted">{{ m.note || '' }}</td><td>{{ m.user?.name || '—' }}</td></tr></tbody></table>
          <p v-if="!d.movements.length" class="empty">{{ tt('لا توجد حركات.','No movements.') }}</p>
        </div>

        <div v-else>
          <table class="table"><thead><tr><th>{{ tt('فاتورة الشراء','Purchase') }}</th><th>{{ tt('التاريخ','Date') }}</th><th>{{ tt('المورد','Supplier') }}</th><th>{{ tt('الكمية','Qty') }}</th><th>{{ tt('تكلفة الوحدة','Unit cost') }}</th><th>{{ tt('الإجمالي','Total') }}</th><th>{{ tt('الحالة','Status') }}</th></tr></thead>
            <tbody><tr v-for="p in d.purchases" :key="p.id"><td><NuxtLink :to="`/inventory/purchases/${p.purchase.id}`" style="color:var(--info)" dir="ltr">{{ p.purchase.number }}</NuxtLink></td><td>{{ f.d(p.purchase.date) }}</td><td>{{ p.purchase.supplier?.name || '—' }}</td><td class="num">{{ f.qty(p.qty) }}</td><td class="num">{{ f.money(p.unit_cost) }}</td><td class="num">{{ f.money(p.total) }}</td><td><span class="badge" :class="purchaseStatus[p.purchase.status]?.cls">{{ purchaseStatus[p.purchase.status]?.label }}</span></td></tr></tbody></table>
          <p v-if="!d.purchases.length" class="empty">{{ tt('لم يُشترَ هذا الصنف بعد.','Not purchased yet.') }}</p>
        </div>
      </div>
    </template>
  </div>
  <div v-else-if="notFound" class="card" style="text-align:center;padding:50px 20px"><i class="ti ti-box-off" style="font-size:48px;color:var(--muted)" /><h2 class="mt">{{ tt('الصنف غير موجود','Item not found') }}</h2><NuxtLink to="/inventory/items" class="btn ghost mt">{{ tt('العودة إلى الأصناف','Back to items') }}</NuxtLink></div>
</template>
