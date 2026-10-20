<script setup lang="ts">
definePageMeta({ title: 'الحجوزات' })
const api = useApi(); const auth = useAuth(); const f = useFmt(); const route = useRoute()
const page = ref<any>(null); const units = ref<any[]>([]); const services = ref<any[]>([]); const customers = ref<any[]>([])
const filt = reactive({ q: '', status: '', unit_id: '', upcoming: false })
const modal = ref(false); const editing = ref<any>(null); const err = ref('')
const blank = () => ({ unit_id: '', customer_id: '', customer_name: '', customer_phone: '', period: 'day', start_at: '', end_at: '', qty: 1, unit_price: 0, discount: 0, deposit: 0, event_type: '', guests: '', note: '', status: 'pending', lines: [] as any[] })
const form = reactive<any>(blank())
const avail = ref<any>(null)
const view = ref<any>(null)
const pay = reactive({ kind: 'payment', amount: 0, method: 'cash', note: '' })
const stLabel: any = { pending: 'معلّق', confirmed: 'مؤكد', completed: 'مكتمل', cancelled: 'ملغى' }
const stClass: any = { pending: 'warn', confirmed: 'ok', completed: 'info', cancelled: 'bad' }
const depLabel: any = { pending: 'لم يُستلم', held: 'محتجز', returned: 'مُرجع', forfeited: 'مُصادر' }
const perLabel: any = { day: 'يوم كامل', night: 'ليلة', hour: 'ساعة' }

async function load() { page.value = await api.get('/app/bookings/list', { ...filt, upcoming: filt.upcoming ? 1 : '' }) }
onMounted(async () => {
  await load()
  units.value = await api.get('/app/bookings/units'); services.value = await api.get('/app/bookings/services')
  customers.value = await api.get('/app/customers', { all: 1, active_only: 1 })
  if (route.query.open) show(Number(route.query.open))
  if (route.query.new) { openNew(); if (typeof route.query.date === 'string') { form.start_at = route.query.date + 'T10:00'; form.end_at = route.query.date + 'T23:00' } }
})
const unit = computed(() => units.value.find(u => u.id === form.unit_id))
watch(() => [form.unit_id, form.period], () => { if (unit.value) { form.unit_price = Number(form.period === 'night' ? unit.value.price_night : form.period === 'hour' ? unit.value.price_hour : unit.value.price_day); form.deposit = Number(unit.value.deposit) } })
watch(() => [form.start_at, form.end_at, form.period], () => {
  if (!form.start_at || !form.end_at) return
  const s = new Date(form.start_at), e = new Date(form.end_at); const ms = e.getTime() - s.getTime()
  form.qty = form.period === 'hour' ? Math.max(1, Math.ceil(ms / 3.6e6)) : Math.max(1, Math.round(ms / 8.64e7) || 1)
  checkAvail()
})
async function checkAvail() {
  if (!form.unit_id || !form.start_at || !form.end_at) { avail.value = null; return }
  try { avail.value = await api.get('/app/bookings/availability', { unit_id: form.unit_id, start_at: form.start_at, end_at: form.end_at, ignore_id: editing.value?.id || '' }) } catch { avail.value = null }
}
const servicesTotal = computed(() => form.lines.reduce((s: number, l: any) => s + l.qty * l.price, 0))
const total = computed(() => { const base = form.qty * form.unit_price + servicesTotal.value - Number(form.discount || 0); const tax = Math.max(0, base) * Number(auth.state.value.tenant?.tax_rate || 0) / 100; return Math.max(0, base) + tax })
function addService(s: any) { const ex = form.lines.find((l: any) => l.service_id === s.id); ex ? ex.qty++ : form.lines.push({ service_id: s.id, name: s.name, qty: 1, price: Number(s.price) }) }
function openNew() { editing.value = null; Object.assign(form, blank()); err.value = ''; avail.value = null; modal.value = true }
function openEdit(b: any) { editing.value = b; Object.assign(form, { ...blank(), ...b, customer_id: b.customer_id || '', customer_name: b.customer_name || '', customer_phone: b.customer_phone || '', event_type: b.event_type || '', guests: b.guests || '', note: b.note || '', start_at: String(b.start_at).slice(0, 16).replace(' ', 'T'), end_at: String(b.end_at).slice(0, 16).replace(' ', 'T'), qty: Number(b.qty), unit_price: Number(b.unit_price), discount: Number(b.discount), deposit: Number(b.deposit), lines: (b.lines || []).map((l: any) => ({ service_id: l.service_id, name: l.name, qty: Number(l.qty), price: Number(l.price) })) }); err.value = ''; view.value = null; modal.value = true }
async function save() {
  err.value = ''
  const body = { ...form, customer_id: form.customer_id || null, guests: form.guests || null }
  try { editing.value ? await api.patch(`/app/bookings/list/${editing.value.id}`, body) : await api.post('/app/bookings/list', body); modal.value = false; await load() }
  catch (e: any) { err.value = e.message }
}
async function show(id: number) { const r = await api.get(`/app/bookings/list/${id}`); view.value = r.booking; pay.amount = Math.max(0, Number(r.booking.total) - Number(r.booking.paid)); pay.kind = 'payment' }
async function setStatus(status: string, deposit_status?: string) { try { await api.post(`/app/bookings/list/${view.value.id}/status`, { status, deposit_status }); await show(view.value.id); await load() } catch (e: any) { alert(e.message) } }
async function doPay() { try { await api.post(`/app/bookings/list/${view.value.id}/pay`, pay); await show(view.value.id); await load() } catch (e: any) { alert(e.message) } }
watch(() => pay.kind, k => { if (!view.value) return; pay.amount = k === 'deposit' ? Math.max(0, Number(view.value.deposit) - Number(view.value.deposit_paid)) : k === 'deposit_return' ? Number(view.value.deposit_paid) : k === 'payment' ? Math.max(0, Number(view.value.total) - Number(view.value.paid)) : 0 })
const canManage = computed(() => auth.can('bookings.manage'))
</script>

<template>
  <div>
    <div class="card">
      <div class="row between" style="margin-bottom:14px">
        <div class="row">
          <input v-model="filt.q" placeholder="رقم / عميل / هاتف" style="padding:8px 12px;border:1px solid var(--line);border-radius:10px" @keyup.enter="load" />
          <select v-model="filt.status" style="padding:8px 12px;border:1px solid var(--line);border-radius:10px" @change="load"><option value="">كل الحالات</option><option v-for="(l, k) in stLabel" :key="k" :value="k">{{ l }}</option></select>
          <select v-model="filt.unit_id" style="padding:8px 12px;border:1px solid var(--line);border-radius:10px" @change="load"><option value="">كل الوحدات</option><option v-for="u in units" :key="u.id" :value="u.id">{{ u.name }}</option></select>
          <label class="switch"><input v-model="filt.upcoming" type="checkbox" @change="load" /><span /> القادمة فقط</label>
          <button class="btn ghost" @click="load"><i class="ti ti-search" /></button>
        </div>
        <div class="row"><NuxtLink to="/bookings/calendar" class="btn ghost"><i class="ti ti-calendar" /> التقويم</NuxtLink><button class="btn" @click="openNew"><i class="ti ti-plus" /> حجز جديد</button></div>
      </div>
      <table class="table">
        <thead><tr><th>رقم الحجز</th><th>الوحدة</th><th>العميل</th><th>النوع</th><th>من</th><th>إلى</th><th>الإجمالي</th><th>مدفوع</th><th>الباقي</th><th>التأمين</th><th>الحالة</th><th></th></tr></thead>
        <tbody>
          <tr v-for="b in page?.data" :key="b.id">
            <td><a href="#" style="color:var(--info);font-weight:700" @click.prevent="show(b.id)">{{ b.number }}</a></td>
            <td><span :style="`display:inline-block;width:10px;height:10px;border-radius:50%;background:${b.unit?.color};margin-left:6px`" />{{ b.unit?.name }}</td>
            <td>{{ b.customer?.name || b.customer_name || '—' }}<br /><small class="muted" dir="ltr">{{ b.customer?.phone || b.customer_phone }}</small></td>
            <td>{{ perLabel[b.period] }} × {{ f.qty(b.qty) }}</td><td>{{ f.dt(b.start_at) }}</td><td>{{ f.dt(b.end_at) }}</td>
            <td class="num"><b>{{ f.money(b.total) }}</b></td><td class="num" style="color:var(--ok)">{{ f.money(b.paid) }}</td><td class="num" style="color:var(--primary)">{{ f.money(Number(b.total) - Number(b.paid)) }}</td>
            <td><span class="badge" :class="b.deposit_status === 'held' ? 'info' : 'muted'">{{ depLabel[b.deposit_status] }}</span></td>
            <td><span class="badge" :class="stClass[b.status]">{{ stLabel[b.status] }}</span></td>
            <td><button class="btn ghost sm icon" @click="show(b.id)"><i class="ti ti-eye" /></button></td>
          </tr>
        </tbody>
      </table>
      <p v-if="page && !page.data.length" class="empty">لا توجد حجوزات.</p>
    </div>

    <UiModal v-if="modal" :title="editing ? `تعديل ${editing.number}` : 'حجز جديد'" lg @close="modal = false">
      <div v-if="err" class="alert bad">{{ err }}</div>
      <div v-if="avail && !avail.available" class="alert bad">الوحدة محجوزة في هذا الوقت: {{ avail.conflicts.map((c: any) => `${c.number} (${stLabel[c.status]})`).join('، ') }}</div>
      <div v-else-if="avail?.available" class="alert ok">الوحدة متاحة ✔</div>
      <div class="grid grid-3">
        <div class="field"><label>الوحدة</label><select v-model="form.unit_id" @change="checkAvail"><option value="">—</option><option v-for="u in units.filter(x => x.is_active)" :key="u.id" :value="u.id">{{ u.name }}</option></select></div>
        <div class="field"><label>نوع الحجز</label><select v-model="form.period"><option value="day">يوم كامل</option><option value="night">ليلة</option><option value="hour">بالساعة</option></select></div>
        <div class="field"><label>نوع المناسبة</label><input v-model="form.event_type" placeholder="زفاف، اجتماع..." /></div>
        <div class="field"><label>من</label><input v-model="form.start_at" type="datetime-local" /></div>
        <div class="field"><label>إلى</label><input v-model="form.end_at" type="datetime-local" /></div>
        <div class="field"><label>عدد الضيوف</label><input v-model.number="form.guests" type="number" dir="ltr" /></div>
        <div class="field"><label>العميل (مسجّل)</label><select v-model="form.customer_id"><option value="">— بدون —</option><option v-for="c in customers" :key="c.id" :value="c.id">{{ c.name }} {{ c.phone ? `— ${c.phone}` : '' }}</option></select></div>
        <div class="field"><label>أو اسم العميل</label><input v-model="form.customer_name" /></div>
        <div class="field"><label>هاتف</label><input v-model="form.customer_phone" dir="ltr" /></div>
      </div>
      <div class="field"><label>خدمات إضافية</label><div class="chips"><span v-for="s in services.filter((x: any) => x.is_active && (!x.unit_id || x.unit_id === form.unit_id))" :key="s.id" class="chip" @click="addService(s)">+ {{ s.name }} <small class="muted">{{ f.money(s.price) }}</small></span></div></div>
      <table v-if="form.lines.length" class="table"><tbody><tr v-for="(l, i) in form.lines" :key="i"><td>{{ l.name }}</td><td style="width:90px"><input v-model.number="l.qty" type="number" step="1" dir="ltr" style="width:100%;padding:4px;border:1px solid var(--line);border-radius:6px" /></td><td style="width:120px"><input v-model.number="l.price" type="number" step="0.001" dir="ltr" style="width:100%;padding:4px;border:1px solid var(--line);border-radius:6px" /></td><td class="num">{{ f.money(l.qty * l.price) }}</td><td><button class="btn ghost sm icon" style="color:var(--primary)" @click="form.lines.splice(i, 1)"><i class="ti ti-x" /></button></td></tr></tbody></table>
      <div class="grid grid-4 mt">
        <div class="field"><label>العدد ({{ perLabel[form.period] }})</label><input v-model.number="form.qty" type="number" step="1" dir="ltr" /></div>
        <div class="field"><label>سعر الوحدة</label><input v-model.number="form.unit_price" type="number" step="0.001" dir="ltr" /></div>
        <div class="field"><label>خصم</label><input v-model.number="form.discount" type="number" step="0.001" dir="ltr" /></div>
        <div class="field"><label>التأمين المطلوب</label><input v-model.number="form.deposit" type="number" step="0.001" dir="ltr" /></div>
      </div>
      <div class="row between"><div class="field" style="flex:1;margin:0"><label>ملاحظة</label><input v-model="form.note" /></div><div style="text-align:left;padding-right:16px"><small class="muted">الإجمالي شامل الضريبة {{ auth.state.value.tenant?.tax_rate }}%</small><br /><b style="font-size:18px;color:var(--primary)">{{ f.money(total, true) }}</b></div></div>
      <label v-if="!editing" class="switch mt"><input type="checkbox" :checked="form.status === 'confirmed'" @change="form.status = ($event.target as HTMLInputElement).checked ? 'confirmed' : 'pending'" /><span /> تأكيد الحجز مباشرة</label>
      <template #footer><button class="btn ghost" @click="modal = false">إلغاء</button><button class="btn" :disabled="avail && !avail.available && form.status === 'confirmed'" @click="save">حفظ</button></template>
    </UiModal>

    <UiModal v-if="view" :title="`حجز ${view.number}`" lg @close="view = null">
      <div class="row between" style="margin-bottom:10px">
        <div class="row" style="gap:20px"><div><small class="muted">الوحدة</small><br /><b>{{ view.unit?.name }}</b></div><div><small class="muted">العميل</small><br /><b>{{ view.customer?.name || view.customer_name || '—' }}</b> <small dir="ltr">{{ view.customer?.phone || view.customer_phone }}</small></div><div><small class="muted">من</small><br /><b>{{ f.dt(view.start_at) }}</b></div><div><small class="muted">إلى</small><br /><b>{{ f.dt(view.end_at) }}</b></div><div><small class="muted">المناسبة</small><br /><b>{{ view.event_type || '—' }} {{ view.guests ? `(${view.guests} ضيف)` : '' }}</b></div></div>
        <span class="badge" :class="stClass[view.status]">{{ stLabel[view.status] }}</span>
      </div>
      <table class="table"><tbody>
        <tr><td>{{ perLabel[view.period] }} × {{ f.qty(view.qty) }}</td><td class="num">{{ f.money(view.unit_price) }}</td><td class="num">{{ f.money(Number(view.qty) * Number(view.unit_price)) }}</td></tr>
        <tr v-for="l in view.lines" :key="l.id"><td>{{ l.name }} × {{ f.qty(l.qty) }}</td><td class="num">{{ f.money(l.price) }}</td><td class="num">{{ f.money(l.total) }}</td></tr>
      </tbody></table>
      <div class="inv-totals">
        <div class="row between"><span>خصم</span><b>{{ f.money(view.discount) }}</b></div>
        <div class="row between"><span>ضريبة {{ view.tax_rate }}%</span><b>{{ f.money(view.tax) }}</b></div>
        <div class="row between" style="font-size:17px;color:var(--primary)"><span>الإجمالي</span><b>{{ f.money(view.total, true) }}</b></div>
        <div class="row between"><span>المدفوع</span><b style="color:var(--ok)">{{ f.money(view.paid) }}</b></div>
        <div class="row between"><span>المتبقي</span><b style="color:var(--primary)">{{ f.money(Number(view.total) - Number(view.paid)) }}</b></div>
        <div class="row between"><span>التأمين ({{ depLabel[view.deposit_status] }})</span><b>{{ f.money(view.deposit_paid) }} / {{ f.money(view.deposit) }}</b></div>
      </div>
      <div v-if="view.payments?.length" class="mt"><small class="muted">الحركات المالية:</small>
        <table class="table"><tbody><tr v-for="p in view.payments" :key="p.id"><td>{{ f.d(p.date) }}</td><td>{{ ({ payment: 'دفعة', deposit: 'تأمين', refund: 'استرداد', deposit_return: 'إرجاع تأمين' } as any)[p.kind] }}</td><td>{{ f.methodLabel(p.method) }}</td><td class="num">{{ f.money(p.amount) }}</td><td class="muted">{{ p.note }}</td></tr></tbody></table></div>
      <p v-if="view.note" class="muted mt">{{ view.note }}</p>
      <template #footer>
        <div class="row" style="width:100%;justify-content:space-between;flex-wrap:wrap">
          <div class="row">
            <button v-if="['pending','confirmed'].includes(view.status)" class="btn ghost" @click="openEdit(view)">تعديل</button>
            <button v-if="view.status === 'pending'" class="btn ok" @click="setStatus('confirmed')">تأكيد</button>
            <button v-if="view.status === 'confirmed'" class="btn info" @click="setStatus('completed', view.deposit_status === 'held' ? 'returned' : undefined)">إكمال</button>
            <button v-if="view.status === 'held' || (view.deposit_status === 'held' && view.status !== 'cancelled')" class="btn ghost" @click="setStatus(view.status, 'forfeited')">مصادرة التأمين</button>
            <button v-if="['pending','confirmed'].includes(view.status)" class="btn ghost" style="color:var(--primary)" @click="confirm('إلغاء الحجز؟') && setStatus('cancelled')">إلغاء الحجز</button>
          </div>
          <div v-if="view.status !== 'cancelled'" class="row">
            <select v-model="pay.kind" style="padding:7px;border:1px solid var(--line);border-radius:8px"><option value="payment">دفعة</option><option value="deposit">استلام تأمين</option><option value="deposit_return">إرجاع تأمين</option><option value="refund">استرداد</option></select>
            <input v-model.number="pay.amount" type="number" step="0.001" dir="ltr" style="width:100px;padding:7px;border:1px solid var(--line);border-radius:8px" />
            <select v-model="pay.method" style="padding:7px;border:1px solid var(--line);border-radius:8px"><option value="cash">نقدي</option><option value="card">بطاقة</option><option value="transfer">تحويل</option></select>
            <button class="btn" @click="doPay">تسجيل</button>
          </div>
        </div>
      </template>
    </UiModal>
  </div>
</template>
