<script setup lang="ts">
/** Full booking form (new + edit): unit & dates with live availability, guest, services, pricing. */
const props = defineProps<{ form: any; units: any[]; services: any[]; customers: any[]; editingId?: number | null; isNew?: boolean }>()
const api = useApi(); const auth = useAuth(); const f = useFmt(); const { tt } = useI18n()
const avail = ref<any>(null)
const taxRate = computed(() => +(auth.state.value.tenant?.tax_rate || 0))
const unit = computed(() => props.units.find((u: any) => String(u.id) === String(props.form.unit_id)))
const totals = computed(() => calcBooking(props.form, taxRate.value))
const customer = computed(() => props.customers.find((c: any) => String(c.id) === String(props.form.customer_id)))
const unitServices = computed(() => props.services.filter((s: any) => s.is_active && (!s.unit_id || String(s.unit_id) === String(props.form.unit_id))))

watch(() => [props.form.unit_id, props.form.period], ([u, p], [ou, op]) => {
  if (!unit.value) return
  if (u !== ou || p !== op) { props.form.unit_price = +(p === 'night' ? unit.value.price_night : p === 'hour' ? unit.value.price_hour : unit.value.price_day) || 0; props.form.deposit = +unit.value.deposit || 0 }
  checkAvail()
})
watch(() => [props.form.start_at, props.form.end_at, props.form.period], () => {
  if (!props.form.start_at || !props.form.end_at) return
  const ms = new Date(props.form.end_at).getTime() - new Date(props.form.start_at).getTime()
  props.form.qty = props.form.period === 'hour' ? Math.max(1, Math.ceil(ms / 3.6e6)) : Math.max(1, Math.round(ms / 8.64e7) || 1)
  checkAvail()
})
watch(() => props.form.customer_id, () => { if (customer.value) { props.form.customer_name = props.form.customer_name || customer.value.name; props.form.customer_phone = props.form.customer_phone || customer.value.phone || ''; props.form.customer_email = props.form.customer_email || customer.value.email || '' } })

let t: any = null
function checkAvail() {
  clearTimeout(t)
  t = setTimeout(async () => {
    if (!props.form.unit_id || !props.form.start_at || !props.form.end_at) { avail.value = null; return }
    try { avail.value = await api.get('/app/bookings/availability', { unit_id: props.form.unit_id, start_at: props.form.start_at, end_at: props.form.end_at, ignore_id: props.editingId || '' }) } catch { avail.value = null }
  }, 250)
}
function addService(s: any) { const ex = props.form.lines.find((l: any) => l.service_id === s.id); ex ? ex.qty++ : props.form.lines.push({ service_id: s.id, name: s.name, qty: 1, price: +s.price }) }
defineExpose({ avail })
</script>

<template>
  <div>
    <div v-if="avail && !avail.available" class="alert bad">{{ tt('الوحدة محجوزة في هذا الوقت:','Unit booked at this time:') }} {{ avail.conflicts.map((c: any) => `${c.number} (${bookingStatus[c.status]?.label})`).join('، ') }}</div>
    <div v-else-if="avail?.available" class="alert ok">{{ tt('الوحدة متاحة في هذه الفترة ✔','Unit available for this period ✔') }}</div>

    <div class="card-title"><i class="ti ti-calendar-event" /> {{ tt('الوحدة والفترة','Unit & period') }}</div>
    <div class="grid grid-3">
      <div class="field"><label>{{ tt('الوحدة','Unit') }} <span style="color:var(--primary)">*</span></label><UiSelect v-model="form.unit_id" :placeholder="tt('اختر الوحدة','Select unit')"><option value="">—</option><option v-for="u in units.filter((x: any) => x.is_active)" :key="u.id" :value="u.id">{{ u.name }}</option></UiSelect><small v-if="unit" class="muted">يوم {{ f.money(unit.price_day) }} · ليلة {{ f.money(unit.price_night) }} · ساعة {{ f.money(unit.price_hour) }}<span v-if="unit.capacity"> · سعة {{ unit.capacity }}</span></small></div>
      <div class="field"><label>{{ tt('نوع الحجز','Booking type') }}</label><UiSelect v-model="form.period"><option v-for="(l, k) in periodLabel" :key="k" :value="k">{{ l }}</option></UiSelect></div>
      <div class="field"><label>{{ tt('مصدر الحجز','Source') }}</label><UiSelect v-model="form.source"><option value="">—</option><option v-for="(l, k) in bookingSource" :key="k" :value="k">{{ l }}</option></UiSelect></div>
      <div class="field"><label>{{ tt('من','From') }} <span style="color:var(--primary)">*</span></label><input v-model="form.start_at" type="datetime-local" dir="ltr" /></div>
      <div class="field"><label>{{ tt('إلى','To') }} <span style="color:var(--primary)">*</span></label><input v-model="form.end_at" type="datetime-local" dir="ltr" /></div>
      <div class="field"><label>{{ tt('العدد','Count') }} ({{ periodLabel[form.period] }})</label><input v-model.number="form.qty" type="number" step="1" min="1" dir="ltr" /></div>
    </div>

    <div class="card-title" style="margin-top:8px"><i class="ti ti-user" /> {{ tt('العميل والضيوف','Customer & guests') }}</div>
    <div class="grid grid-3">
      <div class="field"><label>{{ tt('عميل مسجّل','Registered customer') }}</label><UiSelect v-model="form.customer_id" :placeholder="tt('— بدون —','— none —')"><option value="">{{ tt('— بدون —','— none —') }}</option><option v-for="c in customers" :key="c.id" :value="c.id">{{ c.name }}<template v-if="c.phone"> — {{ c.phone }}</template></option></UiSelect></div>
      <div class="field"><label>{{ tt('اسم العميل','Customer name') }}</label><input v-model="form.customer_name" :placeholder="tt('للحجز السريع بدون تسجيل','Quick booking without registration')" /></div>
      <div class="field"><label>{{ tt('الهاتف','Phone') }}</label><input v-model="form.customer_phone" dir="ltr" /></div>
      <div class="field"><label>{{ tt('البريد الإلكتروني','Email') }}</label><input v-model="form.customer_email" dir="ltr" /></div>
      <div class="field"><label>{{ tt('نوع المناسبة','Event type') }}</label><input v-model="form.event_type" :placeholder="tt('زفاف، اجتماع، إقامة...','Wedding, meeting, stay...')" /></div>
      <div class="field"><label>{{ tt('عدد الضيوف','Guests') }}</label><input v-model="form.guests" type="number" min="0" dir="ltr" /></div>
      <div class="field"><label>{{ tt('بالغون','Adults') }}</label><input v-model="form.adults" type="number" min="0" dir="ltr" /></div>
      <div class="field"><label>{{ tt('أطفال','Children') }}</label><input v-model="form.children" type="number" min="0" dir="ltr" /></div>
    </div>

    <div class="card-title" style="margin-top:8px"><i class="ti ti-sparkles" /> {{ tt('الخدمات الإضافية','Add-on services') }}</div>
    <div class="chips" style="margin-bottom:8px"><span v-for="s in unitServices" :key="s.id" class="chip" @click="addService(s)">+ {{ s.name }} <small class="muted">{{ f.money(s.price) }}</small></span><span v-if="!unitServices.length" class="muted" style="font-size:12px">لا توجد خدمات إضافية مسجّلة.</span></div>
    <table v-if="form.lines.length" class="table lines-editor"><thead><tr><th>{{ tt('الخدمة','Service') }}</th><th style="width:100px">{{ tt('الكمية','Qty') }}</th><th style="width:130px">{{ tt('السعر','Price') }}</th><th style="width:120px">الإجمالي</th><th style="width:48px"></th></tr></thead>
      <tbody><tr v-for="(l, i) in form.lines" :key="i"><td>{{ l.name }}</td><td><input v-model.number="l.qty" type="number" step="1" min="1" dir="ltr" /></td><td><input v-model.number="l.price" type="number" step="0.001" min="0" dir="ltr" /></td><td class="num"><b>{{ f.money((+l.qty || 0) * (+l.price || 0)) }}</b></td><td><button type="button" class="btn ghost sm icon" style="color:var(--primary)" @click="form.lines.splice(i, 1)"><i class="ti ti-x" /></button></td></tr></tbody></table>

    <div class="grid grid-2" style="margin-top:16px;align-items:start">
      <div>
        <div class="card-title"><i class="ti ti-cash" /> {{ tt('التسعير والتأمين','Pricing & deposit') }}</div>
        <div class="grid grid-3">
          <div class="field"><label>{{ tt('سعر الوحدة','Unit price') }}</label><input v-model.number="form.unit_price" type="number" step="0.001" min="0" dir="ltr" /></div>
          <div class="field"><label>{{ tt('خصم','Discount') }}</label><input v-model.number="form.discount" type="number" step="0.001" min="0" dir="ltr" /></div>
          <div class="field"><label>{{ tt('التأمين المطلوب','Required deposit') }}</label><input v-model.number="form.deposit" type="number" step="0.001" min="0" dir="ltr" /></div>
        </div>
        <div class="card-title" style="margin-top:8px"><i class="ti ti-note" /> {{ tt('الملاحظات','Notes') }}</div>
        <div class="field"><label>{{ tt('طلبات خاصة من العميل','Special requests') }}</label><textarea v-model="form.special_requests" rows="2" /></div>
        <div class="field"><label>{{ tt('ملاحظة على الحجز','Booking note') }}</label><input v-model="form.note" /></div>
        <div class="field"><label>{{ tt('ملاحظات داخلية (للموظفين فقط)','Internal notes (staff only)') }}</label><textarea v-model="form.internal_note" rows="2" /></div>
        <label v-if="isNew" class="switch"><input type="checkbox" :checked="form.status === 'confirmed'" @change="form.status = ($event.target as HTMLInputElement).checked ? 'confirmed' : 'pending'" /><span /> {{ tt('تأكيد الحجز مباشرة','Confirm immediately') }}</label>
      </div>
      <div class="totals-box">
        <div class="row between"><span>{{ periodLabel[form.period] }} × {{ form.qty }}</span><b>{{ f.money((+form.qty || 0) * (+form.unit_price || 0)) }}</b></div>
        <div class="row between"><span>{{ tt('الخدمات الإضافية','Add-ons') }}</span><b>{{ f.money(totals.services) }}</b></div>
        <div class="row between"><span>{{ tt('الخصم','Discount') }}</span><b>- {{ f.money(form.discount) }}</b></div>
        <div class="row between"><span>{{ tt('الضريبة','Tax') }} ({{ taxRate }}%)</span><b>{{ f.money(totals.tax) }}</b></div>
        <div class="row between total"><span>{{ tt('الإجمالي','Total') }}</span><b>{{ f.money(totals.total, true) }}</b></div>
        <div class="row between"><span>{{ tt('التأمين (يُسترد)','Deposit (refundable)') }}</span><b>{{ f.money(form.deposit) }}</b></div>
      </div>
    </div>
  </div>
</template>
