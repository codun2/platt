<script setup lang="ts">
definePageMeta({ title: 'سجل الوحدة', titleEn: 'Unit record' })
const api = useApi(); const auth = useAuth(); const f = useFmt(); const route = useRoute()
const id = route.params.id as string
const canManage = computed(() => auth.can('serials.manage'))
const u = ref<any>(null); const err = ref(''); const msg = ref(''); const busy = ref(false)
const panel = ref<'' | 'edit' | 'faulty' | 'replace'>('')
const form = reactive<any>({ warranty_start: '', warranty_months: '', location: '', note: '' })
const fault = reactive({ note: '' })
const repl = reactive<any>({ replacement_id: '', note: '' })
const stock = ref<any[]>([])
async function load() { u.value = await api.get(`/app/serials/${id}`) }
onMounted(load)
usePageTitle(() => u.value ? u.value.serial_no : null)
const w = computed(() => u.value ? warrantyState(u.value) : null)
const name = computed(() => u.value?.customer?.name || u.value?.customer_name)

function openEdit() { panel.value = 'edit'; err.value = ''; Object.assign(form, { warranty_start: u.value.warranty_start || '', warranty_months: u.value.warranty_months || '', location: u.value.location || '', note: u.value.note || '' }) }
async function openReplace() {
  panel.value = 'replace'; err.value = ''; Object.assign(repl, { replacement_id: '', note: '' })
  try { const r = await api.get('/app/serials', { scope: 'in_stock', item_id: u.value.item_id }); stock.value = r.data || [] } catch { stock.value = [] }
}
async function run(fn: () => Promise<any>, ok: string) {
  err.value = ''; busy.value = true
  try { await fn(); msg.value = ok; panel.value = ''; await load() } catch (e: any) { err.value = e.message } finally { busy.value = false }
}
const saveEdit = () => run(() => api.patch(`/app/serials/${id}`, { warranty_start: form.warranty_start || null, warranty_months: form.warranty_months || null, location: form.location || null, note: form.note || null }), tt('تم الحفظ', 'Saved'))
const markFaulty = () => run(() => api.post(`/app/serials/${id}/status`, { status: 'faulty', note: fault.note || null }), tt('سُجّل العطل', 'Marked faulty'))
const setStatus = (s: string) => run(() => api.post(`/app/serials/${id}/status`, { status: s }), tt('تم التحديث', 'Updated'))
const doReplace = () => run(() => api.post(`/app/serials/${id}/replace`, { replacement_id: repl.replacement_id, note: repl.note || null }), tt('تم الاستبدال تحت الضمان', 'Replaced'))
const evLabel: Record<string, string> = { received: tt('استلام', 'Received'), opening: tt('رصيد افتتاحي', 'Opening'), delivered: tt('تسليم للعميل', 'Delivered'), returned: tt('إرجاع', 'Returned'), faulty: tt('تسجيل عطل', 'Marked faulty'), replaced: tt('استبدال', 'Replaced'), scrapped: tt('إتلاف', 'Scrapped'), edited: tt('تعديل', 'Edited') }
</script>

<template>
  <div v-if="u">
    <div class="breadcrumb"><NuxtLink to="/serials">{{ tt('الأرقام التسلسلية', 'Serials') }}</NuxtLink> › <b dir="ltr">{{ u.serial_no }}</b></div>
    <div v-if="msg" class="alert ok">{{ msg }}</div>

    <div class="card">
      <div class="row between" style="flex-wrap:wrap;gap:10px">
        <div>
          <h2 style="margin:0" dir="ltr">{{ u.serial_no }}</h2>
          <div class="muted" style="margin-top:4px">{{ u.item?.name }}<span v-if="u.item?.sku" dir="ltr"> · {{ u.item.sku }}</span> · <span class="badge" :class="serialStatus[u.status]?.cls"><i :class="`ti ${serialStatus[u.status]?.icon}`" /> {{ serialStatus[u.status]?.label }}</span></div>
        </div>
        <div v-if="canManage" class="row" style="gap:6px;flex-wrap:wrap">
          <button v-if="['sold','installed'].includes(u.status)" class="btn ghost sm" style="color:var(--primary)" @click="panel = 'faulty'"><i class="ti ti-alert-triangle" /> {{ tt('تسجيل عطل', 'Mark faulty') }}</button>
          <button v-if="u.status === 'faulty' && !u.replaced_by_id" class="btn sm" @click="openReplace"><i class="ti ti-refresh" /> {{ tt('استبدال تحت الضمان', 'Replace') }}</button>
          <button v-if="u.status === 'faulty'" class="btn ghost sm" @click="setStatus('scrapped')"><i class="ti ti-trash" /> {{ tt('إتلاف', 'Scrap') }}</button>
          <button v-if="['sold','installed'].includes(u.status)" class="btn ghost sm" @click="setStatus('returned')"><i class="ti ti-arrow-back-up" /> {{ tt('إرجاع للمخزن', 'Return') }}</button>
          <button class="btn ghost sm" @click="openEdit"><i class="ti ti-pencil" /> {{ tt('تعديل الضمان', 'Edit warranty') }}</button>
        </div>
      </div>

      <div v-if="panel" class="panel">
        <div v-if="err" class="alert bad">{{ err }}</div>
        <template v-if="panel === 'edit'">
          <div class="grid grid-2">
            <div class="field"><label>{{ tt('بداية الضمان', 'Warranty start') }}</label><input v-model="form.warranty_start" type="date" dir="ltr" /></div>
            <div class="field"><label>{{ tt('مدة الضمان (شهور)', 'Warranty (months)') }}</label><input v-model.number="form.warranty_months" type="number" min="0" dir="ltr" /></div>
            <div class="field"><label>{{ tt('الموقع', 'Location') }}</label><input v-model="form.location" /></div>
            <div class="field"><label>{{ tt('ملاحظة', 'Note') }}</label><input v-model="form.note" /></div>
          </div>
          <div class="row"><button class="btn" :disabled="busy" @click="saveEdit">{{ tt('حفظ', 'Save') }}</button><button class="btn ghost" @click="panel = ''">{{ tt('إلغاء', 'Cancel') }}</button></div>
        </template>
        <template v-if="panel === 'faulty'">
          <div class="field"><label>{{ tt('وصف العطل', 'Fault description') }}</label><input v-model="fault.note" :placeholder="tt('مثال: توقف الإنتاج / كسر', 'e.g. no output / cracked')" /></div>
          <div class="row"><button class="btn" style="background:var(--primary)" :disabled="busy" @click="markFaulty"><i class="ti ti-alert-triangle" /> {{ tt('تسجيل العطل', 'Mark faulty') }}</button><button class="btn ghost" @click="panel = ''">{{ tt('إلغاء', 'Cancel') }}</button></div>
        </template>
        <template v-if="panel === 'replace'">
          <p class="muted" style="margin-top:0">{{ tt('اختر وحدة من المخزن (نفس الصنف) لتسليمها للعميل بدل الوحدة المعطوبة — بضمان جديد.', 'Pick an in-stock unit (same item) to give the customer instead — with a fresh warranty.') }}</p>
          <div class="grid grid-2">
            <div class="field"><label>{{ tt('الوحدة البديلة', 'Replacement unit') }}</label><UiSelect v-model="repl.replacement_id"><option value="">{{ tt('اختر رقماً من المخزن', 'Pick an in-stock serial') }}</option><option v-for="s in stock" :key="s.id" :value="s.id">{{ s.serial_no }}</option></UiSelect><small v-if="!stock.length" class="muted">{{ tt('لا توجد وحدة من هذا الصنف في المخزن.', 'No in-stock unit of this item.') }}</small></div>
            <div class="field"><label>{{ tt('ملاحظة', 'Note') }}</label><input v-model="repl.note" /></div>
          </div>
          <div class="row"><button class="btn" :disabled="busy || !repl.replacement_id" @click="doReplace"><i class="ti ti-refresh" /> {{ tt('تأكيد الاستبدال', 'Confirm') }}</button><button class="btn ghost" @click="panel = ''">{{ tt('إلغاء', 'Cancel') }}</button></div>
        </template>
      </div>
    </div>

    <div class="grid grid-2 mt" style="align-items:start">
      <div class="card">
        <div class="card-title"><i class="ti ti-shield" /> {{ tt('الضمان والملكية', 'Warranty & ownership') }}</div>
        <div v-if="w" class="alert" :class="w.cls" style="margin-top:0"><i class="ti ti-shield-check" /> {{ w.label }}</div>
        <table class="table"><tbody>
          <tr><td class="muted">{{ tt('العميل', 'Customer') }}</td><td><template v-if="name">{{ name }}<span v-if="u.customer?.phone" dir="ltr"> · {{ u.customer.phone }}</span></template><span v-else class="muted">—</span></td></tr>
          <tr><td class="muted">{{ tt('الموقع', 'Location') }}</td><td>{{ u.location || '—' }}</td></tr>
          <tr><td class="muted">{{ tt('تاريخ التسليم', 'Delivered on') }}</td><td dir="ltr">{{ u.delivered_at || '—' }}</td></tr>
          <tr><td class="muted">{{ tt('بداية الضمان', 'Warranty start') }}</td><td dir="ltr">{{ u.warranty_start || '—' }}</td></tr>
          <tr><td class="muted">{{ tt('انتهاء الضمان', 'Warranty until') }}</td><td dir="ltr"><b>{{ u.warranty_until || '—' }}</b><small v-if="u.warranty_months" class="muted"> ({{ u.warranty_months }} {{ tt('شهر', 'mo') }})</small></td></tr>
          <tr><td class="muted">{{ tt('المستند', 'Document') }}</td><td>{{ u.out_ref || '—' }}</td></tr>
          <tr v-if="u.warehouse"><td class="muted">{{ tt('المستودع', 'Warehouse') }}</td><td>{{ u.warehouse.name }}</td></tr>
        </tbody></table>
      </div>
      <div class="card">
        <div class="card-title"><i class="ti ti-history" /> {{ tt('سجل الوحدة', 'Timeline') }}</div>
        <div class="timeline">
          <div v-for="e in u.events" :key="e.id" class="ev">
            <div class="dot" /><div>
              <b>{{ evLabel[e.type] || e.type }}</b><span v-if="e.to_status && e.to_status !== e.from_status" class="muted"> → {{ serialStatus[e.to_status]?.label }}</span>
              <div v-if="e.note || e.ref" class="muted" style="font-size:12px">{{ e.note }}<span v-if="e.ref" dir="ltr"> · {{ e.ref }}</span></div>
              <small class="muted">{{ f.dt(e.created_at) }}<span v-if="e.user"> · {{ e.user.name }}</span></small>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
.panel { margin-top: 14px; border-top: 1px solid var(--line); padding-top: 14px; }
.timeline { position: relative; padding-inline-start: 6px; }
.ev { display: flex; gap: 12px; padding: 8px 0; position: relative; }
.ev .dot { width: 10px; height: 10px; border-radius: 50%; background: var(--primary); margin-top: 5px; flex-shrink: 0; position: relative; }
.ev:not(:last-child) .dot::after { content: ''; position: absolute; top: 12px; inset-inline-start: 4px; width: 2px; height: calc(100% + 10px); background: var(--line); }
</style>
