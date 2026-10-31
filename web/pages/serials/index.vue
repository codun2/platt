<script setup lang="ts">
definePageMeta({ title: 'الأرقام التسلسلية والضمان', titleEn: 'Serials & Warranty' })
const api = useApi(); const auth = useAuth(); const f = useFmt()
const canManage = computed(() => auth.can('serials.manage'))
const sum = ref<any>(null); const list = ref<any>(null); const untracked = ref<any[]>([])
const filt = reactive({ scope: '', days: 30, q: '', page: 1 })
const scopes = computed(() => [['', tt('الكل', 'All')], ['in_stock', tt('في المخزن', 'In stock')], ['under_warranty', tt('ضمان سارٍ', 'Under warranty')], ['expiring', tt('يقارب الانتهاء', 'Expiring')], ['expired', tt('انتهى الضمان', 'Expired')], ['faulty', tt('أعطال', 'Faulty')]])
// Lookup (scan)
const scan = ref(''); const hit = ref<any>(null); const scanning = ref(false); const notFound = ref(false)
// Register / deliver dialogs
const dlg = ref<'' | 'register' | 'deliver'>(''); const err = ref(''); const msg = ref(''); const busy = ref(false)
const items = ref<any[]>([]); const warehouses = ref<any[]>([]); const customers = ref<any[]>([])
const reg = reactive<any>({ item_id: '', warehouse_id: '', warranty_months: '', raw: '', note: '' })
const del = reactive<any>({ as: 'installed', customer_id: '', customer_name: '', out_ref: '', delivered_at: f.today(), location: '', note: '' })
const picked = ref<Set<number>>(new Set())

async function load(p: any = 1) {
  filt.page = typeof p === 'number' ? p : 1
  ;[sum.value, list.value] = await Promise.all([api.get('/app/serials/summary', { days: filt.days }), api.get('/app/serials', filt)])
}
async function loadUntracked() { untracked.value = await api.get('/app/serials/reconcile') }
onMounted(async () => {
  await load(); loadUntracked()
  try { const r = await api.get('/app/inventory/items/lookup', { q: '' }); items.value = (r.items || []).filter((i: any) => i.track_serial) } catch { /* */ }
  try { warehouses.value = await api.get('/app/inventory/warehouses') } catch { /* */ }
  try { customers.value = await api.get('/app/customers', { all: 1, active_only: 1 }) } catch { /* */ }
})
watch(() => filt.scope, () => load())

async function doScan() {
  const s = scan.value.trim(); if (!s) return
  scanning.value = true; notFound.value = false; hit.value = null
  try { const r = await api.get('/app/serials/lookup', { serial: s }); if (r.found) hit.value = r; else notFound.value = true } catch (e: any) { uiAlert(e.message) } finally { scanning.value = false }
}
function openRegister() { dlg.value = 'register'; err.value = ''; Object.assign(reg, { item_id: '', warehouse_id: warehouses.value.find((w: any) => w.is_default)?.id || warehouses.value[0]?.id || '', warranty_months: '', raw: '', note: '' }) }
const regSerials = computed(() => reg.raw.split(/[\n,،\t ]+/).map((x: string) => x.trim()).filter(Boolean))
watch(() => reg.item_id, id => { const it = items.value.find((i: any) => i.id === id); if (it && !reg.warranty_months) reg.warranty_months = it.warranty_months || '' })
async function saveRegister() {
  err.value = ''; if (!regSerials.value.length) { err.value = tt('أدخل رقماً تسلسلياً واحداً على الأقل.', 'Enter at least one serial.'); return }
  busy.value = true
  try {
    const r = await api.post('/app/serials/register', { item_id: reg.item_id, warehouse_id: reg.warehouse_id, serials: regSerials.value, warranty_months: reg.warranty_months || null, note: reg.note || null })
    msg.value = tt(`تم تسجيل ${r.created} وحدة`, `Registered ${r.created} units`) + (r.skipped.length ? tt(` (${r.skipped.length} مكرّر تم تجاهله)`, ` (${r.skipped.length} duplicates skipped)`) : '')
    dlg.value = ''; await Promise.all([load(filt.page), loadUntracked()])
  } catch (e: any) { err.value = e.message } finally { busy.value = false }
}
function toggle(id: number) { picked.value.has(id) ? picked.value.delete(id) : picked.value.add(id); picked.value = new Set(picked.value) }
function openDeliver() { if (!picked.value.size) return; dlg.value = 'deliver'; err.value = ''; Object.assign(del, { as: 'installed', customer_id: '', customer_name: '', out_ref: '', delivered_at: f.today(), location: '', note: '' }) }
async function saveDeliver() {
  err.value = ''; busy.value = true
  try {
    const r = await api.post('/app/serials/deliver', { ids: [...picked.value], ...del, customer_id: del.customer_id || null, customer_name: del.customer_name || null })
    msg.value = tt(`تم تسليم ${r.count} وحدة`, `Delivered ${r.count} units`); dlg.value = ''; picked.value = new Set(); await Promise.all([load(filt.page), loadUntracked()])
  } catch (e: any) { err.value = e.message } finally { busy.value = false }
}
</script>

<template>
  <div>
    <PageHelp id="serials-home" :title="tt('الأرقام التسلسلية والضمان: تتبّع كل وحدة من المخزن إلى العميل', 'Serials & warranty: track each unit from stock to the customer')">
      {{ tt('سجّل كل لوح/إنفرتر/بطارية برقمه التسلسلي عند الاستلام، وعند تسليمه للعميل يُحتسب تاريخ الضمان تلقائياً. ابحث بأي رقم تسلسلي لتعرف أين هو ومن يملكه وهل ضمانه سارٍ — أساس مطالبات الضمان.', 'Register each panel/inverter/battery by serial on receipt; when handed to a customer the warranty end is computed automatically. Look up any serial to see where it is, who owns it and whether it is under warranty.') }}
    </PageHelp>

    <div v-if="sum" class="grid grid-4">
      <div class="card stat" style="cursor:pointer" @click="filt.scope = 'in_stock'"><div class="ic" style="background:var(--info)"><i class="ti ti-building-warehouse" /></div><div><b>{{ sum.in_stock }}</b><small>{{ tt('وحدة في المخزن', 'units in stock') }} · {{ sum.total }} {{ tt('إجمالاً', 'total') }}</small></div></div>
      <div class="card stat" style="cursor:pointer" @click="filt.scope = 'under_warranty'"><div class="ic" style="background:var(--ok)"><i class="ti ti-shield-check" /></div><div><b>{{ sum.under_warranty }}</b><small>{{ tt('تحت الضمان عند العملاء', 'under warranty') }}</small></div></div>
      <div class="card stat" style="cursor:pointer" @click="filt.scope = 'expiring'"><div class="ic" style="background:var(--warn)"><i class="ti ti-shield-x" /></div><div><b>{{ sum.expiring }} / {{ sum.expired }}</b><small>{{ tt('يقارب الانتهاء / منتهٍ', 'expiring / expired') }}</small></div></div>
      <div class="card stat" style="cursor:pointer" @click="filt.scope = 'faulty'"><div class="ic" style="background:var(--primary)"><i class="ti ti-alert-triangle" /></div><div><b>{{ sum.faulty }}</b><small>{{ tt('أعطال بانتظار الاستبدال', 'faulty / to replace') }}</small></div></div>
    </div>

    <!-- Scan / lookup -->
    <div class="card mt">
      <div class="card-title"><i class="ti ti-search" /> {{ tt('بحث بالرقم التسلسلي', 'Look up a serial') }}</div>
      <div class="row"><input v-model="scan" :placeholder="tt('أدخل الرقم التسلسلي ثم Enter', 'Enter serial then Enter')" dir="ltr" style="flex:1;max-width:360px;padding:10px 14px;border:1px solid var(--line);border-radius:10px;font-size:15px" autofocus @keyup.enter="doScan" /><button class="btn" :disabled="scanning" @click="doScan"><i :class="`ti ${scanning ? 'ti-loader-2 spin' : 'ti-search'}`" /> {{ tt('بحث', 'Find') }}</button></div>
      <p v-if="notFound" class="alert warn mt">{{ tt('لا توجد وحدة بهذا الرقم التسلسلي.', 'No unit with this serial.') }}</p>
      <div v-if="hit" class="hit mt">
        <div class="row between" style="flex-wrap:wrap;gap:8px">
          <div><b style="font-size:16px" dir="ltr">{{ hit.unit.serial_no }}</b> — {{ hit.unit.item?.name }} <span class="badge" :class="serialStatus[hit.unit.status]?.cls"><i :class="`ti ${serialStatus[hit.unit.status]?.icon}`" /> {{ serialStatus[hit.unit.status]?.label }}</span></div>
          <NuxtLink :to="`/serials/${hit.unit.id}`" class="btn ghost sm">{{ tt('فتح السجل الكامل', 'Open record') }} <i class="ti ti-arrow-left" /></NuxtLink>
        </div>
        <div class="row" style="gap:20px;flex-wrap:wrap;margin-top:8px;font-size:13px">
          <span v-if="hit.unit.customer || hit.unit.customer_name"><i class="ti ti-user" /> {{ hit.unit.customer?.name || hit.unit.customer_name }}<span v-if="hit.unit.customer?.phone" dir="ltr"> · {{ hit.unit.customer.phone }}</span></span>
          <span v-if="hit.unit.warehouse"><i class="ti ti-building-warehouse" /> {{ hit.unit.warehouse.name }}</span>
          <span v-if="hit.unit.warranty_until"><i class="ti ti-shield" /> {{ tt('الضمان حتى', 'Warranty until') }} <b dir="ltr">{{ hit.unit.warranty_until }}</b> — <b :style="{ color: hit.warranty.active ? 'var(--ok)' : 'var(--primary)' }">{{ hit.warranty.active ? tt(`سارٍ (${Math.round(hit.warranty.days_left / 30)} شهر)`, `active (${hit.warranty.days_left}d)`) : tt('منتهٍ', 'expired') }}</b></span>
          <span v-if="hit.unit.out_ref"><i class="ti ti-file" /> {{ hit.unit.out_ref }}</span>
        </div>
      </div>
    </div>

    <!-- Reconcile hint -->
    <div v-if="untracked.length && canManage" class="card mt">
      <div class="card-title"><i class="ti ti-alert-circle" style="color:var(--warn)" /> {{ tt('مخزون بلا أرقام تسلسلية مسجّلة', 'Stock without registered serials') }}</div>
      <table class="table"><thead><tr><th>{{ tt('الصنف', 'Item') }}</th><th>{{ tt('المخزون', 'Stock') }}</th><th>{{ tt('مسجّل', 'Registered') }}</th><th>{{ tt('غير مسجّل', 'Missing') }}</th><th></th></tr></thead>
        <tbody><tr v-for="r in untracked" :key="r.item.id"><td><b>{{ r.item.name }}</b></td><td class="num">{{ f.qty(r.stock) }}</td><td class="num">{{ r.serials }}</td><td class="num" style="color:var(--warn)">{{ f.qty(r.missing) }}</td><td><button class="btn ghost sm" @click="openRegister(); reg.item_id = r.item.id"><i class="ti ti-plus" /> {{ tt('تسجيل أرقام', 'Register') }}</button></td></tr></tbody>
      </table>
    </div>

    <!-- Units list -->
    <div class="card mt">
      <div v-if="msg" class="alert ok">{{ msg }}</div>
      <div class="row between" style="flex-wrap:wrap;gap:8px;margin-bottom:12px">
        <div class="chips"><span v-for="[k, l] in scopes" :key="k" class="chip" :class="{ on: filt.scope === k }" @click="filt.scope = k">{{ l }}</span></div>
        <div class="row" style="gap:8px">
          <input v-model="filt.q" :placeholder="tt('رقم / عميل / مستند', 'Serial / customer / ref')" style="padding:8px 12px;border:1px solid var(--line);border-radius:10px;min-width:200px" @keyup.enter="load()" />
          <button v-if="canManage" class="btn" @click="openRegister"><i class="ti ti-plus" /> {{ tt('تسجيل أرقام', 'Register serials') }}</button>
        </div>
      </div>
      <div v-if="canManage && picked.size" class="alert info row between"><span>{{ tt(`محدّد ${picked.size} وحدة`, `${picked.size} selected`) }}</span><button class="btn sm" @click="openDeliver"><i class="ti ti-user-check" /> {{ tt('تسليم للعميل', 'Deliver to customer') }}</button></div>
      <table class="table">
        <thead><tr><th v-if="canManage" style="width:30px"></th><th>{{ tt('الرقم التسلسلي', 'Serial') }}</th><th>{{ tt('الصنف', 'Item') }}</th><th>{{ tt('الحالة', 'Status') }}</th><th>{{ tt('العميل / الموقع', 'Customer') }}</th><th>{{ tt('الضمان', 'Warranty') }}</th></tr></thead>
        <tbody>
          <tr v-for="u in list?.data" :key="u.id">
            <td v-if="canManage"><input v-if="u.status === 'in_stock'" type="checkbox" :checked="picked.has(u.id)" @change="toggle(u.id)" /></td>
            <td><NuxtLink :to="`/serials/${u.id}`" dir="ltr" style="color:var(--info);font-weight:700">{{ u.serial_no }}</NuxtLink></td>
            <td>{{ u.item?.name }}</td>
            <td><span class="badge" :class="serialStatus[u.status]?.cls"><i :class="`ti ${serialStatus[u.status]?.icon}`" /> {{ serialStatus[u.status]?.label }}</span></td>
            <td><template v-if="u.customer || u.customer_name">{{ u.customer?.name || u.customer_name }}<br /><small class="muted">{{ u.location || u.out_ref }}</small></template><span v-else class="muted">{{ u.warehouse?.name || '—' }}</span></td>
            <td><template v-if="warrantyState(u)"><span class="badge" :class="warrantyState(u)!.cls">{{ warrantyState(u)!.label }}</span><br /><small class="muted" dir="ltr">{{ u.warranty_until }}</small></template><span v-else class="muted">—</span></td>
          </tr>
        </tbody>
      </table>
      <p v-if="list && !list.data.length" class="empty">{{ tt('لا توجد وحدات مطابقة. ابدأ بتفعيل «رقم تسلسلي» على الصنف من شاشة الأصناف، ثم سجّل الأرقام هنا.', 'No units. Turn on serial tracking on an item, then register serials here.') }}</p>
      <div v-if="list?.data.length" class="mt"><UiPager :p="list" @go="load" /></div>
    </div>

    <!-- Register dialog -->
    <UiModal v-if="dlg === 'register'" :title="tt('تسجيل أرقام تسلسلية', 'Register serials')" @close="dlg = ''">
      <div v-if="err" class="alert bad">{{ err }}</div>
      <div class="grid grid-2">
        <div class="field"><label>{{ tt('الصنف', 'Item') }} <span style="color:var(--primary)">*</span></label><UiSelect v-model="reg.item_id"><option value="">{{ tt('اختر صنفاً له رقم تسلسلي', 'Pick a serialized item') }}</option><option v-for="i in items" :key="i.id" :value="i.id">{{ i.name }}</option></UiSelect><small v-if="!items.length" class="muted">{{ tt('لا يوجد صنف مفعّل له رقم تسلسلي بعد — فعّله من شاشة الصنف.', 'No serialized items yet — enable it on the item.') }}</small></div>
        <div class="field"><label>{{ tt('المستودع', 'Warehouse') }} <span style="color:var(--primary)">*</span></label><UiSelect v-model="reg.warehouse_id"><option v-for="w in warehouses" :key="w.id" :value="w.id">{{ w.name }}</option></UiSelect></div>
      </div>
      <div class="field"><label>{{ tt('الأرقام التسلسلية', 'Serial numbers') }} <span style="color:var(--primary)">*</span> <small class="muted">({{ tt('كل رقم في سطر، أو افصلها بفاصلة', 'one per line or comma-separated') }})</small></label><textarea v-model="reg.raw" rows="6" dir="ltr" placeholder="EUP-0001&#10;EUP-0002&#10;EUP-0003" /><small class="muted">{{ tt(`عدد الأرقام: ${regSerials.length}`, `${regSerials.length} serials`) }}</small></div>
      <div class="grid grid-2">
        <div class="field"><label>{{ tt('مدة الضمان (شهور)', 'Warranty (months)') }}</label><input v-model.number="reg.warranty_months" type="number" min="0" dir="ltr" :placeholder="tt('من الصنف', 'from item')" /></div>
        <div class="field"><label>{{ tt('ملاحظة', 'Note') }}</label><input v-model="reg.note" /></div>
      </div>
      <template #footer><button class="btn ghost" @click="dlg = ''">{{ tt('إلغاء', 'Cancel') }}</button><button class="btn" :disabled="busy || !reg.item_id || !regSerials.length" @click="saveRegister"><i class="ti ti-check" /> {{ tt('تسجيل', 'Register') }} {{ regSerials.length || '' }}</button></template>
    </UiModal>

    <!-- Deliver dialog -->
    <UiModal v-if="dlg === 'deliver'" :title="tt(`تسليم ${picked.size} وحدة للعميل`, `Deliver ${picked.size} units`)" @close="dlg = ''">
      <div v-if="err" class="alert bad">{{ err }}</div>
      <div class="chips" style="margin-bottom:10px"><span class="chip" :class="{ on: del.as === 'installed' }" @click="del.as = 'installed'"><i class="ti ti-tool" /> {{ tt('تركيب', 'Installed') }}</span><span class="chip" :class="{ on: del.as === 'sold' }" @click="del.as = 'sold'"><i class="ti ti-shopping-cart" /> {{ tt('بيع', 'Sold') }}</span></div>
      <div class="grid grid-2">
        <div class="field"><label>{{ tt('العميل', 'Customer') }}</label><UiSelect v-model="del.customer_id"><option value="">{{ tt('— أو اكتب الاسم أدناه', '— or type a name') }}</option><option v-for="c in customers" :key="c.id" :value="c.id">{{ c.name }}{{ c.phone ? ` — ${c.phone}` : '' }}</option></UiSelect></div>
        <div v-if="!del.customer_id" class="field"><label>{{ tt('اسم العميل', 'Customer name') }}</label><input v-model="del.customer_name" /></div>
        <div class="field"><label>{{ tt('تاريخ التسليم', 'Delivered on') }}</label><input v-model="del.delivered_at" type="date" dir="ltr" /></div>
        <div class="field"><label>{{ tt('رقم المستند (فاتورة/أمر)', 'Document (invoice/job)') }}</label><input v-model="del.out_ref" dir="ltr" /></div>
        <div v-if="del.as === 'installed'" class="field" style="grid-column:1/-1"><label>{{ tt('موقع التركيب', 'Installation site') }}</label><input v-model="del.location" /></div>
      </div>
      <p class="muted" style="font-size:13px"><i class="ti ti-shield" /> {{ tt('يبدأ الضمان من تاريخ التسليم ويُحتسب تاريخ انتهائه تلقائياً من مدة ضمان كل صنف.', 'Warranty starts on the delivery date; its end is computed from each item’s warranty period.') }}</p>
      <template #footer><button class="btn ghost" @click="dlg = ''">{{ tt('إلغاء', 'Cancel') }}</button><button class="btn" :disabled="busy" @click="saveDeliver"><i class="ti ti-user-check" /> {{ tt('تسليم', 'Deliver') }}</button></template>
    </UiModal>
  </div>
</template>

<style scoped>
.hit { background: var(--bg); border: 1px solid var(--line); border-radius: 12px; padding: 12px 14px; }
</style>
