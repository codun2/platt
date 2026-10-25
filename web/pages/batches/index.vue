<script setup lang="ts">
definePageMeta({ title: 'الصلاحية والدفعات', titleEn: 'Expiry & Batches' })
const api = useApi(); const auth = useAuth(); const f = useFmt(); const route = useRoute()
const canManage = computed(() => auth.can('batches.manage'))
const sum = ref<any>(null); const list = ref<any>(null); const untracked = ref<any[]>([]); const warehouses = ref<any[]>([])
const filt = reactive({ scope: String(route.query.scope || 'soon'), days: 30, q: '', warehouse_id: '', page: 1 })
const scopes = computed(() => [['expired', tt('منتهية الصلاحية', 'Expired')], ['soon', tt('تنتهي قريباً', 'Expiring soon')], ['all', tt('كل الدفعات المتوفرة', 'All in stock')], ['undated', tt('بلا تاريخ', 'No date')], ['finished', tt('نفدت', 'Used up')]])
const act = ref<any>(null); const actKind = ref<'writeoff' | 'edit' | ''>(''); const w = reactive({ qty: 0, reason: '', expires_on: '', batch_no: '' })
const assign = reactive<any>({ row: null, qty: 0, expires_on: '', batch_no: '' })
const err = ref(''); const msg = ref('')

async function load(p: any = 1) {
  filt.page = typeof p === 'number' ? p : 1
  ;[sum.value, list.value] = await Promise.all([api.get('/app/batches/summary', { days: filt.days }), api.get('/app/batches', filt)])
}
async function loadUntracked() { untracked.value = await api.get('/app/batches/untracked') }
onMounted(async () => { await load(); loadUntracked(); try { warehouses.value = await api.get('/app/inventory/warehouses') } catch { /* */ } })
const daysLeft = (d: string) => d ? Math.round((new Date(d + 'T00:00:00').getTime() - new Date(new Date().toDateString()).getTime()) / 86400000) : null
const expiryCls = (b: any) => { const n = daysLeft(b.expires_on); return n === null ? 'muted' : n < 0 ? 'bad' : n <= 7 ? 'warn' : n <= filt.days ? 'info' : 'ok' }
const expiryText = (b: any) => { const n = daysLeft(b.expires_on); return n === null ? tt('بلا تاريخ', 'No date') : n < 0 ? tt(`منتهٍ منذ ${-n} يوم`, `expired ${-n}d ago`) : n === 0 ? tt('ينتهي اليوم', 'expires today') : tt(`باقٍ ${n} يوم`, `${n}d left`) }
function open(b: any, kind: 'writeoff' | 'edit') { act.value = b; actKind.value = kind; err.value = ''; Object.assign(w, { qty: +b.qty_left, reason: daysLeft(b.expires_on)! < 0 ? tt('منتهي الصلاحية', 'Expired') : '', expires_on: b.expires_on || '', batch_no: b.batch_no || '' }) }
async function submit() {
  err.value = ''
  try {
    if (actKind.value === 'writeoff') { await api.post(`/app/batches/${act.value.id}/write-off`, { qty: w.qty, reason: w.reason || null }); msg.value = tt('تم الإتلاف وتسجيل الخسارة', 'Written off') }
    else { await api.patch(`/app/batches/${act.value.id}`, { expires_on: w.expires_on || null, batch_no: w.batch_no || null }); msg.value = tt('تم تعديل الدفعة', 'Batch updated') }
    act.value = null; await load(filt.page)
  } catch (e: any) { err.value = e.message }
}
function startAssign(r: any) { Object.assign(assign, { row: r, qty: r.untracked, expires_on: '', batch_no: '' }); err.value = '' }
async function doAssign() {
  err.value = ''
  try { await api.post('/app/batches/assign', { item_id: assign.row.item_id, warehouse_id: assign.row.warehouse_id, qty: assign.qty, expires_on: assign.expires_on, batch_no: assign.batch_no || null }); assign.row = null; msg.value = tt('تم تسجيل الدفعة', 'Batch recorded'); await Promise.all([load(), loadUntracked()]) }
  catch (e: any) { err.value = e.message }
}
</script>

<template>
  <div>
    <div v-if="sum" class="grid grid-4">
      <div class="card stat" style="cursor:pointer" @click="filt.scope = 'expired'; load()"><div class="ic" style="background:var(--primary)"><i class="ti ti-calendar-x" /></div><div><b>{{ sum.expired }}</b><small>{{ tt('دفعات منتهية بقيمة', 'Expired batches worth') }} {{ f.money(sum.expired_value) }}</small></div></div>
      <div class="card stat" style="cursor:pointer" @click="filt.scope = 'soon'; load()"><div class="ic" style="background:var(--warn)"><i class="ti ti-alarm" /></div><div><b>{{ sum.week }}</b><small>{{ tt('تنتهي خلال 7 أيام', 'Expire within 7 days') }}</small></div></div>
      <div class="card stat" style="cursor:pointer" @click="filt.scope = 'soon'; load()"><div class="ic" style="background:var(--info)"><i class="ti ti-calendar-due" /></div><div><b>{{ sum.soon }}</b><small>{{ tt(`تنتهي خلال ${sum.days} يوماً بقيمة`, `Within ${sum.days} days, worth`) }} {{ f.money(sum.soon_value) }}</small></div></div>
      <div class="card stat"><div class="ic" style="background:var(--ok)"><i class="ti ti-box" /></div><div><b>{{ sum.tracked_items }}</b><small>{{ tt('أصناف تُتتبَّع صلاحيتها', 'Items tracked') }}</small></div></div>
    </div>
    <div v-if="msg" class="alert ok mt">{{ msg }}</div>

    <div v-if="untracked.length && canManage" class="card mt">
      <div class="card-title"><i class="ti ti-alert-circle" style="color:var(--warn)" /> {{ tt('مخزون بلا تاريخ صلاحية بعد', 'Stock without an expiry yet') }}</div>
      <p class="muted" style="margin-top:0">{{ tt('هذه كميات كانت موجودة قبل تفعيل تتبع الصلاحية. سجّل تاريخها مرة واحدة (بدون أي حركة مخزون).', 'Stock that existed before tracking started. Record its date once (no stock movement).') }}</p>
      <table class="table">
        <thead><tr><th>{{ tt('الصنف', 'Item') }}</th><th>{{ tt('المستودع', 'Warehouse') }}</th><th>{{ tt('بلا دفعة', 'Without batch') }}</th><th></th></tr></thead>
        <tbody>
          <tr v-for="r in untracked" :key="`${r.item_id}-${r.warehouse_id}`">
            <td><b>{{ r.item?.name }}</b></td><td>{{ r.warehouse?.name }}</td><td class="num">{{ f.qty(r.untracked) }}</td>
            <td>
              <div v-if="assign.row === r" class="row" style="gap:6px;flex-wrap:wrap">
                <input v-model.number="assign.qty" type="number" min="0" step="0.001" dir="ltr" style="width:90px" />
                <input v-model="assign.expires_on" type="date" dir="ltr" />
                <input v-model="assign.batch_no" dir="ltr" :placeholder="tt('رقم الدفعة', 'Batch no.')" style="width:110px" />
                <button class="btn sm" :disabled="!assign.expires_on" @click="doAssign">{{ tt('حفظ', 'Save') }}</button><button class="btn ghost sm" @click="assign.row = null">{{ tt('إلغاء', 'Cancel') }}</button>
              </div>
              <button v-else class="btn ghost sm" @click="startAssign(r)"><i class="ti ti-calendar-plus" /> {{ tt('تسجيل التاريخ', 'Set expiry') }}</button>
            </td>
          </tr>
        </tbody>
      </table>
      <div v-if="err && assign.row" class="alert bad">{{ err }}</div>
    </div>

    <div class="card mt">
      <div class="row between" style="flex-wrap:wrap;gap:8px;margin-bottom:12px">
        <div class="chips"><span v-for="[k, l] in scopes" :key="k" class="chip" :class="{ on: filt.scope === k }" @click="filt.scope = k; load()">{{ l }}</span></div>
        <div class="row" style="gap:8px;flex-wrap:wrap">
          <UiSelect v-if="filt.scope === 'soon'" v-model.number="filt.days" style="min-width:130px" @change="load()"><option :value="7">{{ tt('7 أيام', '7 days') }}</option><option :value="30">{{ tt('30 يوماً', '30 days') }}</option><option :value="60">{{ tt('60 يوماً', '60 days') }}</option><option :value="90">{{ tt('90 يوماً', '90 days') }}</option></UiSelect>
          <UiSelect v-if="warehouses.length > 1" v-model="filt.warehouse_id" style="min-width:150px" @change="load()"><option value="">{{ tt('كل المستودعات', 'All warehouses') }}</option><option v-for="x in warehouses" :key="x.id" :value="x.id">{{ x.name }}</option></UiSelect>
          <input v-model="filt.q" :placeholder="tt('صنف / باركود / رقم دفعة', 'Item / barcode / batch')" style="padding:8px 12px;border:1px solid var(--line);border-radius:10px;min-width:200px" @keyup.enter="load()" />
        </div>
      </div>
      <table class="table">
        <thead><tr><th>{{ tt('الصنف', 'Item') }}</th><th>{{ tt('الدفعة', 'Batch') }}</th><th>{{ tt('المستودع', 'Warehouse') }}</th><th>{{ tt('الصلاحية', 'Expiry') }}</th><th>{{ tt('المتبقي', 'Left') }}</th><th>{{ tt('القيمة', 'Value') }}</th><th></th></tr></thead>
        <tbody>
          <template v-for="b in list?.data" :key="b.id">
            <tr>
              <td><b>{{ b.item?.name }}</b><br /><small class="muted" dir="ltr">{{ b.item?.sku }}</small></td>
              <td dir="ltr" style="text-align:right">{{ b.batch_no || '—' }}<br /><small class="muted">{{ tt('استلام', 'in') }} {{ f.qty(b.qty_in) }}</small></td>
              <td>{{ b.warehouse?.name }}</td>
              <td><span dir="ltr">{{ b.expires_on || '—' }}</span><br /><span class="badge" :class="expiryCls(b)">{{ expiryText(b) }}</span></td>
              <td class="num"><b>{{ f.qty(b.qty_left) }}</b> <small class="muted">{{ b.item?.unit?.symbol }}</small></td>
              <td class="num">{{ f.money(+b.qty_left * +(b.unit_cost || 0)) }}</td>
              <td v-if="canManage && +b.qty_left > 0" class="row" style="gap:4px"><button class="btn ghost sm" style="color:var(--primary)" @click="open(b, 'writeoff')"><i class="ti ti-trash-x" /> {{ tt('إتلاف', 'Write off') }}</button><button class="btn ghost sm icon" @click="open(b, 'edit')"><i class="ti ti-pencil" /></button></td>
              <td v-else />
            </tr>
            <tr v-if="act && act.id === b.id"><td colspan="7" style="background:var(--bg)">
              <div v-if="err" class="alert bad">{{ err }}</div>
              <div v-if="actKind === 'writeoff'" class="row" style="gap:8px;flex-wrap:wrap;align-items:end">
                <div class="field" style="margin:0"><label>{{ tt('الكمية المتلفة', 'Qty') }}</label><input v-model.number="w.qty" type="number" min="0" step="0.001" dir="ltr" style="width:110px" /></div>
                <div class="field" style="margin:0;flex:1;min-width:200px"><label>{{ tt('السبب', 'Reason') }}</label><input v-model="w.reason" /></div>
                <span class="muted">{{ tt('خسارة', 'Loss') }} {{ f.money((+w.qty || 0) * +(b.unit_cost || 0)) }}</span>
                <button class="btn" style="background:var(--primary)" @click="submit">{{ tt('تأكيد الإتلاف', 'Confirm') }}</button><button class="btn ghost" @click="act = null">{{ tt('إلغاء', 'Cancel') }}</button>
              </div>
              <div v-else class="row" style="gap:8px;flex-wrap:wrap;align-items:end">
                <div class="field" style="margin:0"><label>{{ tt('الصلاحية', 'Expiry') }}</label><input v-model="w.expires_on" type="date" dir="ltr" /></div>
                <div class="field" style="margin:0"><label>{{ tt('رقم الدفعة', 'Batch no.') }}</label><input v-model="w.batch_no" dir="ltr" /></div>
                <button class="btn" @click="submit">{{ tt('حفظ', 'Save') }}</button><button class="btn ghost" @click="act = null">{{ tt('إلغاء', 'Cancel') }}</button>
              </div>
            </td></tr>
          </template>
        </tbody>
      </table>
      <p v-if="list && !list.data.length" class="empty">{{ filt.scope === 'expired' ? tt('لا توجد دفعات منتهية 🎉', 'Nothing expired 🎉') : tt('لا توجد دفعات.', 'No batches.') }}</p>
      <div v-if="list?.data.length" class="mt"><UiPager :p="list" @go="load" /></div>
      <p class="muted" style="margin-bottom:0;font-size:12px"><i class="ti ti-info-circle" /> {{ tt('عند البيع والتحويل والإنتاج يُصرف من الدفعة الأقرب انتهاءً تلقائياً، وإلغاء الفاتورة يعيد الكمية لنفس الدفعات. فعّل «تتبع الصلاحية» من شاشة الصنف، وأدخل التاريخ عند استلام المشتريات.', 'Sales, transfers and production take the earliest expiry first automatically; cancelling puts it back into the same batches. Turn on expiry tracking on the item, and enter the date when receiving purchases.') }}</p>
    </div>
  </div>
</template>
