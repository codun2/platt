<script setup lang="ts">
definePageMeta({ title: 'ملف الحجز', titleEn: 'Booking' })
const api = useApi(); const auth = useAuth(); const f = useFmt(); const { tt } = useI18n()
const route = useRoute(); const id = Number(route.params.id)
const b = ref<any>(null); const units = ref<any[]>([]); const services = ref<any[]>([]); const customers = ref<any[]>([])
const editing = ref(route.query.edit === '1'); const paying = ref(false)
const err = ref(''); const msg = ref(''); const saving = ref(false); const notFound = ref(false)
const form = reactive<any>(blankBooking())
const pay = reactive({ kind: 'payment', amount: 0, method: 'cash', date: f.today(), note: '' })
const canManage = computed(() => auth.can('bookings.manage'))
const balance = computed(() => Math.max(0, +b.value.total - +b.value.paid))
const open = computed(() => ['pending', 'confirmed'].includes(b.value?.status))
const startsIn = computed(() => daysUntil(b.value?.start_at ? String(b.value.start_at).slice(0, 10) : null))

async function load() {
  try { b.value = (await api.get(`/app/bookings/list/${id}`)).booking } catch (e: any) { if (e.status === 404) { notFound.value = true; return } throw e }
  Object.assign(form, bookingToForm(b.value)); pay.amount = balance.value; pay.kind = 'payment'
}
onMounted(async () => { await load(); units.value = await api.get('/app/bookings/units'); services.value = await api.get('/app/bookings/services'); customers.value = await api.get('/app/customers', { all: 1 }) })
watch(() => pay.kind, k => { if (!b.value) return; pay.amount = k === 'deposit' ? Math.max(0, +b.value.deposit - +b.value.deposit_paid) : k === 'deposit_return' ? +b.value.deposit_paid : k === 'payment' ? balance.value : 0 })

async function act(fn: () => Promise<any>, ok = tt('تم','Done')) {
  err.value = ''; msg.value = ''; saving.value = true
  try { await fn(); msg.value = ok; await load() } catch (e: any) { err.value = e.message } finally { saving.value = false; window.scrollTo({ top: 0, behavior: 'smooth' }) }
}
const save = () => act(async () => { await api.patch(`/app/bookings/list/${id}`, bookingPayload(form)); editing.value = false }, tt('تم حفظ التعديلات','Changes saved'))
const setStatus = (status: string, deposit_status?: string, label = tt('تم','Done')) => act(() => api.post(`/app/bookings/list/${id}/status`, { status, deposit_status }), label)
const checkin = (action: 'in' | 'out') => act(() => api.post(`/app/bookings/list/${id}/checkin`, { action }), action === 'in' ? tt('تم تسجيل الوصول','Checked in') : tt('تم تسجيل المغادرة','Checked out'))
const doPay = () => act(async () => { await api.post(`/app/bookings/list/${id}/pay`, pay); paying.value = false; Object.assign(pay, { method: 'cash', note: '', date: f.today() }) }, tt('تم تسجيل الحركة المالية','Payment recorded'))
</script>

<template>
  <div v-if="b">
    <div class="breadcrumb"><NuxtLink to="/bookings">{{ tt('الحجوزات','Bookings') }}</NuxtLink> › <b dir="ltr">{{ b.number }}</b></div>
    <div v-if="msg" class="alert ok">{{ msg }}</div>
    <div v-if="err" class="alert bad">{{ err }}</div>

    <div v-if="editing" class="card">
      <BookingForm :form="form" :units="units" :services="services" :customers="customers" :editing-id="id" />
      <div class="row" style="gap:10px;margin-top:18px;border-top:1px solid var(--line);padding-top:16px">
        <button class="btn" :disabled="saving" @click="save"><i class="ti ti-check" /> {{ tt('حفظ التعديلات','Save changes') }}</button>
        <button class="btn ghost" :disabled="saving" @click="editing = false; load()">{{ tt('إلغاء','Cancel') }}</button>
      </div>
    </div>

    <template v-else>
      <div class="grid grid-4" style="margin-bottom:16px">
        <div class="card stat"><div class="ic" style="background:var(--info)"><i class="ti ti-calendar-event" /></div><div><b>{{ f.money(b.total, true) }}</b><small>{{ tt('إجمالي الحجز','Booking total') }} · {{ periodLabel[b.period] }} × {{ f.qty(b.qty) }}</small></div></div>
        <div class="card stat"><div class="ic" style="background:var(--ok)"><i class="ti ti-cash" /></div><div><b>{{ f.money(b.paid) }}</b><small>{{ tt('المدفوع','Paid') }}</small></div></div>
        <div class="card stat"><div class="ic" :style="`background:${balance > 0 ? 'var(--primary)' : 'var(--ok)'}`"><i class="ti ti-wallet" /></div><div><b>{{ f.money(balance) }}</b><small>{{ tt('المتبقي','Balance') }}</small></div></div>
        <div class="card stat"><div class="ic" :style="`background:${b.deposit_status === 'held' ? 'var(--info)' : 'var(--warn)'}`"><i class="ti ti-shield-check" /></div><div><b>{{ f.money(b.deposit_paid) }} / {{ f.money(b.deposit) }}</b><small>{{ tt('التأمين','Deposit') }} · {{ depositStatus[b.deposit_status] }}</small></div></div>
      </div>

      <div class="card">
        <div class="row between" style="align-items:flex-start">
          <div>
            <div class="row" style="gap:10px;align-items:center">
              <h2 style="margin:0" dir="ltr">{{ b.number }}</h2>
              <span class="badge" :class="bookingStatus[b.status]?.cls">{{ bookingStatus[b.status]?.label }}</span>
              <span v-if="b.checked_in_at && !b.checked_out_at" class="badge info">{{ tt('مقيم الآن','Checked in') }}</span>
            </div>
            <p class="muted" style="margin:6px 0 0"><span :style="`display:inline-block;width:10px;height:10px;border-radius:50%;background:${b.unit?.color};margin-left:6px`" />{{ b.unit?.name }} · {{ f.dt(b.start_at) }} ← {{ f.dt(b.end_at) }}<span v-if="open && startsIn !== null"> · {{ startsIn > 0 ? `بعد ${startsIn} يوم` : startsIn === 0 ? 'اليوم' : 'بدأ' }}</span><span v-if="b.creator"> · أنشأه {{ b.creator.name }}</span></p>
          </div>
          <div v-if="canManage" class="row" style="gap:8px;flex-wrap:wrap;justify-content:flex-end">
            <button v-if="b.status === 'pending'" class="btn ok sm" @click="setStatus('confirmed', undefined, tt('تم تأكيد الحجز','Booking confirmed'))"><i class="ti ti-check" /> {{ tt('تأكيد','Confirm') }}</button>
            <button v-if="open && !b.checked_in_at" class="btn info sm" @click="checkin('in')"><i class="ti ti-login" /> {{ tt('تسجيل وصول','Check in') }}</button>
            <button v-if="open && b.checked_in_at && !b.checked_out_at" class="btn info sm" @click="checkin('out')"><i class="ti ti-logout" /> {{ tt('تسجيل مغادرة','Check out') }}</button>
            <button v-if="b.status === 'confirmed'" class="btn ghost sm" @click="setStatus('completed', b.deposit_status === 'held' ? 'returned' : undefined, tt('تم إكمال الحجز','Booking completed'))"><i class="ti ti-flag-check" /> {{ tt('إكمال','Complete') }}</button>
            <button v-if="b.status !== 'cancelled'" class="btn ghost sm" @click="paying = !paying"><i class="ti ti-cash" /> {{ tt('حركة مالية','Transaction') }}</button>
            <button v-if="b.deposit_status === 'held' && b.status !== 'cancelled'" class="btn ghost sm" @click="setStatus(b.status, 'forfeited', tt('تمت مصادرة التأمين','Deposit forfeited'))"><i class="ti ti-shield-x" /> {{ tt('مصادرة التأمين','Forfeit deposit') }}</button>
            <button v-if="open" class="btn ghost sm" @click="editing = true"><i class="ti ti-pencil" /> {{ tt('تعديل','Edit') }}</button>
            <button v-if="open" class="btn ghost sm" style="color:var(--primary)" @click="confirm(tt('إلغاء الحجز؟','Cancel booking?')) && setStatus('cancelled', undefined, tt('تم إلغاء الحجز','Booking cancelled'))"><i class="ti ti-ban" /> {{ tt('إلغاء الحجز','Cancel booking') }}</button>
          </div>
        </div>

        <div v-if="paying" class="pay-panel mt">
          <div class="card-title" style="margin-bottom:8px"><i class="ti ti-cash" /> {{ tt('حركة مالية','Transaction') }} — {{ tt('المتبقي','balance') }} {{ f.money(balance, true) }}</div>
          <div class="row" style="align-items:flex-end;gap:10px">
            <div class="field" style="margin:0"><label>{{ tt('النوع','Type') }}</label><UiSelect v-model="pay.kind" style="width:160px"><option v-for="(l, k) in paymentKind" :key="k" :value="k">{{ l }}</option></UiSelect></div>
            <div class="field" style="margin:0"><label>{{ tt('المبلغ','Amount') }}</label><input v-model.number="pay.amount" type="number" step="0.001" min="0" dir="ltr" style="width:130px" /></div>
            <div class="field" style="margin:0"><label>{{ tt('الطريقة','Method') }}</label><UiSelect v-model="pay.method" style="width:130px"><option value="cash">{{ tt('نقدي','Cash') }}</option><option value="card">{{ tt('بطاقة','Card') }}</option><option value="transfer">{{ tt('تحويل','Transfer') }}</option></UiSelect></div>
            <div class="field" style="margin:0"><label>{{ tt('التاريخ','Date') }}</label><input v-model="pay.date" type="date" dir="ltr" /></div>
            <div class="field" style="margin:0;flex:1"><label>{{ tt('ملاحظة','Note') }}</label><input v-model="pay.note" /></div>
            <button class="btn" :disabled="saving || !pay.amount" @click="doPay"><i class="ti ti-check" /> {{ tt('تسجيل','Record') }}</button>
            <button class="btn ghost" @click="paying = false">{{ tt('إغلاق','Close') }}</button>
          </div>
        </div>

        <hr style="border:none;border-top:1px solid var(--line);margin:16px 0">
        <div class="grid grid-3">
          <div><small class="muted">{{ tt('العميل','Customer') }}</small><br /><b v-if="b.customer"><NuxtLink :to="`/crm/customers/${b.customer.id}`" style="color:var(--info)">{{ b.customer.name }}</NuxtLink></b><b v-else>{{ b.customer_name || '—' }}</b><div class="muted" style="font-size:12px" dir="ltr">{{ [b.customer?.phone || b.customer_phone, b.customer_email].filter(Boolean).join(' · ') }}</div></div>
          <div><small class="muted">{{ tt('المناسبة / المصدر','Event / source') }}</small><br /><b>{{ b.event_type || '—' }}</b><span v-if="b.source" class="muted"> · {{ bookingSource[b.source] }}</span></div>
          <div><small class="muted">{{ tt('الضيوف','Guests') }}</small><br /><b>{{ b.guests ?? '—' }}</b><span v-if="b.adults != null || b.children != null" class="muted"> ({{ b.adults || 0 }} بالغ · {{ b.children || 0 }} طفل)</span></div>
          <div><small class="muted">{{ tt('الوصول','Check-in') }}</small><br /><b>{{ b.checked_in_at ? f.dt(b.checked_in_at) : '—' }}</b></div>
          <div><small class="muted">{{ tt('المغادرة','Check-out') }}</small><br /><b>{{ b.checked_out_at ? f.dt(b.checked_out_at) : '—' }}</b></div>
          <div><small class="muted">{{ tt('ملاحظة','Note') }}</small><br /><b>{{ b.note || '—' }}</b></div>
          <div v-if="b.special_requests" style="grid-column:1/-1"><small class="muted">{{ tt('طلبات خاصة','Special requests') }}</small><br />{{ b.special_requests }}</div>
          <div v-if="b.internal_note" style="grid-column:1/-1;background:var(--bg);padding:10px;border-radius:10px"><small class="muted"><i class="ti ti-lock" /> {{ tt('ملاحظات داخلية','Internal notes') }}</small><br />{{ b.internal_note }}</div>
        </div>
      </div>

      <div class="card">
        <div class="card-title"><i class="ti ti-list-details" /> {{ tt('التفاصيل المالية','Financial details') }}</div>
        <table class="table"><thead><tr><th>{{ tt('البند','Item') }}</th><th>{{ tt('الكمية','Qty') }}</th><th>{{ tt('السعر','Price') }}</th><th>{{ tt('الإجمالي','Total') }}</th></tr></thead><tbody>
          <tr><td>{{ b.unit?.name }} — {{ periodLabel[b.period] }}</td><td class="num">{{ f.qty(b.qty) }}</td><td class="num">{{ f.money(b.unit_price) }}</td><td class="num"><b>{{ f.money(+b.qty * +b.unit_price) }}</b></td></tr>
          <tr v-for="l in b.lines" :key="l.id"><td>{{ l.name }}</td><td class="num">{{ f.qty(l.qty) }}</td><td class="num">{{ f.money(l.price) }}</td><td class="num"><b>{{ f.money(l.total) }}</b></td></tr>
        </tbody></table>
        <div class="totals-box" style="margin-right:auto;max-width:340px">
          <div class="row between"><span>{{ tt('الخصم','Discount') }}</span><b>- {{ f.money(b.discount) }}</b></div>
          <div class="row between"><span>{{ tt('الضريبة','Tax') }} ({{ b.tax_rate }}%)</span><b>{{ f.money(b.tax) }}</b></div>
          <div class="row between total"><span>{{ tt('الإجمالي','Total') }}</span><b>{{ f.money(b.total, true) }}</b></div>
          <div class="row between"><span>{{ tt('المدفوع','Paid') }}</span><b>{{ f.money(b.paid) }}</b></div>
          <div class="row between"><span>{{ tt('المتبقي','Balance') }}</span><b :style="balance > 0 ? 'color:var(--primary)' : ''">{{ f.money(balance) }}</b></div>
        </div>
      </div>

      <div class="card">
        <div class="card-title"><i class="ti ti-history" /> {{ tt('الحركات المالية','Transactions') }} ({{ b.payments?.length || 0 }})</div>
        <table class="table"><thead><tr><th>{{ tt('التاريخ','Date') }}</th><th>{{ tt('النوع','Type') }}</th><th>{{ tt('الطريقة','Method') }}</th><th>{{ tt('المبلغ','Amount') }}</th><th>{{ tt('ملاحظة','Note') }}</th></tr></thead>
          <tbody><tr v-for="p in b.payments" :key="p.id"><td>{{ f.d(p.date) }}</td><td>{{ paymentKind[p.kind] }}</td><td>{{ f.methodLabel(p.method) }}</td><td class="num"><b :style="['refund','deposit_return'].includes(p.kind) ? 'color:var(--primary)' : 'color:var(--ok)'">{{ f.money(p.amount) }}</b></td><td class="muted">{{ p.note || '' }}</td></tr></tbody></table>
        <p v-if="!b.payments?.length" class="empty">{{ tt('لا توجد حركات مالية بعد.','No transactions yet.') }}</p>
      </div>
    </template>
  </div>
  <div v-else-if="notFound" class="card" style="text-align:center;padding:50px 20px"><i class="ti ti-calendar-off" style="font-size:48px;color:var(--muted)" /><h2 class="mt">{{ tt('الحجز غير موجود','Booking not found') }}</h2><NuxtLink to="/bookings" class="btn ghost mt">{{ tt('العودة إلى الحجوزات','Back to bookings') }}</NuxtLink></div>
</template>
