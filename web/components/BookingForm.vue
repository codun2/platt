<script setup lang="ts">
/** Full booking form (new + edit): unit & dates with live availability, guest, services, pricing. */
const props = defineProps<{ form: any; units: any[]; services: any[]; customers: any[]; editingId?: number | null; isNew?: boolean }>()
const api = useApi(); const auth = useAuth(); const f = useFmt()
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
    <div v-if="avail && !avail.available" class="alert bad">الوحدة محجوزة في هذا الوقت: {{ avail.conflicts.map((c: any) => `${c.number} (${bookingStatus[c.status]?.label})`).join('، ') }}</div>
    <div v-else-if="avail?.available" class="alert ok">الوحدة متاحة في هذه الفترة ✔</div>

    <div class="card-title"><i class="ti ti-calendar-event" /> الوحدة والفترة</div>
    <div class="grid grid-3">
      <div class="field"><label>الوحدة <span style="color:var(--primary)">*</span></label><UiSelect v-model="form.unit_id" placeholder="اختر الوحدة"><option value="">—</option><option v-for="u in units.filter((x: any) => x.is_active)" :key="u.id" :value="u.id">{{ u.name }}</option></UiSelect><small v-if="unit" class="muted">يوم {{ f.money(unit.price_day) }} · ليلة {{ f.money(unit.price_night) }} · ساعة {{ f.money(unit.price_hour) }}<span v-if="unit.capacity"> · سعة {{ unit.capacity }}</span></small></div>
      <div class="field"><label>نوع الحجز</label><UiSelect v-model="form.period"><option v-for="(l, k) in periodLabel" :key="k" :value="k">{{ l }}</option></UiSelect></div>
      <div class="field"><label>مصدر الحجز</label><UiSelect v-model="form.source"><option value="">—</option><option v-for="(l, k) in bookingSource" :key="k" :value="k">{{ l }}</option></UiSelect></div>
      <div class="field"><label>من <span style="color:var(--primary)">*</span></label><input v-model="form.start_at" type="datetime-local" dir="ltr" /></div>
      <div class="field"><label>إلى <span style="color:var(--primary)">*</span></label><input v-model="form.end_at" type="datetime-local" dir="ltr" /></div>
      <div class="field"><label>العدد ({{ periodLabel[form.period] }})</label><input v-model.number="form.qty" type="number" step="1" min="1" dir="ltr" /></div>
    </div>

    <div class="card-title" style="margin-top:8px"><i class="ti ti-user" /> العميل والضيوف</div>
    <div class="grid grid-3">
      <div class="field"><label>عميل مسجّل</label><UiSelect v-model="form.customer_id" placeholder="— بدون —"><option value="">— بدون —</option><option v-for="c in customers" :key="c.id" :value="c.id">{{ c.name }}<template v-if="c.phone"> — {{ c.phone }}</template></option></UiSelect></div>
      <div class="field"><label>اسم العميل</label><input v-model="form.customer_name" placeholder="للحجز السريع بدون تسجيل" /></div>
      <div class="field"><label>الهاتف</label><input v-model="form.customer_phone" dir="ltr" /></div>
      <div class="field"><label>البريد الإلكتروني</label><input v-model="form.customer_email" dir="ltr" /></div>
      <div class="field"><label>نوع المناسبة</label><input v-model="form.event_type" placeholder="زفاف، اجتماع، إقامة..." /></div>
      <div class="field"><label>عدد الضيوف</label><input v-model="form.guests" type="number" min="0" dir="ltr" /></div>
      <div class="field"><label>بالغون</label><input v-model="form.adults" type="number" min="0" dir="ltr" /></div>
      <div class="field"><label>أطفال</label><input v-model="form.children" type="number" min="0" dir="ltr" /></div>
    </div>

    <div class="card-title" style="margin-top:8px"><i class="ti ti-sparkles" /> الخدمات الإضافية</div>
    <div class="chips" style="margin-bottom:8px"><span v-for="s in unitServices" :key="s.id" class="chip" @click="addService(s)">+ {{ s.name }} <small class="muted">{{ f.money(s.price) }}</small></span><span v-if="!unitServices.length" class="muted" style="font-size:12px">لا توجد خدمات إضافية مسجّلة.</span></div>
    <table v-if="form.lines.length" class="table lines-editor"><thead><tr><th>الخدمة</th><th style="width:100px">الكمية</th><th style="width:130px">السعر</th><th style="width:120px">الإجمالي</th><th style="width:48px"></th></tr></thead>
      <tbody><tr v-for="(l, i) in form.lines" :key="i"><td>{{ l.name }}</td><td><input v-model.number="l.qty" type="number" step="1" min="1" dir="ltr" /></td><td><input v-model.number="l.price" type="number" step="0.001" min="0" dir="ltr" /></td><td class="num"><b>{{ f.money((+l.qty || 0) * (+l.price || 0)) }}</b></td><td><button type="button" class="btn ghost sm icon" style="color:var(--primary)" @click="form.lines.splice(i, 1)"><i class="ti ti-x" /></button></td></tr></tbody></table>

    <div class="grid grid-2" style="margin-top:16px;align-items:start">
      <div>
        <div class="card-title"><i class="ti ti-cash" /> التسعير والتأمين</div>
        <div class="grid grid-3">
          <div class="field"><label>سعر الوحدة</label><input v-model.number="form.unit_price" type="number" step="0.001" min="0" dir="ltr" /></div>
          <div class="field"><label>خصم</label><input v-model.number="form.discount" type="number" step="0.001" min="0" dir="ltr" /></div>
          <div class="field"><label>التأمين المطلوب</label><input v-model.number="form.deposit" type="number" step="0.001" min="0" dir="ltr" /></div>
        </div>
        <div class="card-title" style="margin-top:8px"><i class="ti ti-note" /> الملاحظات</div>
        <div class="field"><label>طلبات خاصة من العميل</label><textarea v-model="form.special_requests" rows="2" /></div>
        <div class="field"><label>ملاحظة على الحجز</label><input v-model="form.note" /></div>
        <div class="field"><label>ملاحظات داخلية (للموظفين فقط)</label><textarea v-model="form.internal_note" rows="2" /></div>
        <label v-if="isNew" class="switch"><input type="checkbox" :checked="form.status === 'confirmed'" @change="form.status = ($event.target as HTMLInputElement).checked ? 'confirmed' : 'pending'" /><span /> تأكيد الحجز مباشرة</label>
      </div>
      <div class="totals-box">
        <div class="row between"><span>{{ periodLabel[form.period] }} × {{ form.qty }}</span><b>{{ f.money((+form.qty || 0) * (+form.unit_price || 0)) }}</b></div>
        <div class="row between"><span>الخدمات الإضافية</span><b>{{ f.money(totals.services) }}</b></div>
        <div class="row between"><span>الخصم</span><b>- {{ f.money(form.discount) }}</b></div>
        <div class="row between"><span>الضريبة ({{ taxRate }}%)</span><b>{{ f.money(totals.tax) }}</b></div>
        <div class="row between total"><span>الإجمالي</span><b>{{ f.money(totals.total, true) }}</b></div>
        <div class="row between"><span>التأمين (يُسترد)</span><b>{{ f.money(form.deposit) }}</b></div>
      </div>
    </div>
  </div>
</template>
