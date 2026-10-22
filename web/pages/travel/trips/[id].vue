<script setup lang="ts">
definePageMeta({ title: 'ملف الرحلة', titleEn: 'Trip Profile' })
const api = useApi(); const auth = useAuth(); const f = useFmt(); const { tt } = useI18n()
const route = useRoute(); const id = Number(route.params.id)

const view = ref<any>(null); const packages = ref<any[]>([])
const editing = ref(route.query.edit === '1'); const err = ref(''); const msg = ref(''); const saving = ref(false)
const form = reactive<any>(blankTrip())
const trav = reactive<any>({ name: '', phone: '', passport_no: '', nationality: '', gender: '', package_id: '', price: '' })
const exp = reactive({ category: 'flights', amount: 0, note: '' })

const typeLabel: any = { umrah: L('عمرة','Umrah'), hajj: L('حج','Hajj'), tourism: L('سياحة','Tourism') }
const stLabel: any = { scheduled: L('مجدولة','Scheduled'), ongoing: L('جارية','Ongoing'), completed: L('مكتملة','Completed'), cancelled: L('ملغاة','Cancelled') }
const stClass: any = { scheduled: 'info', ongoing: 'ok', completed: 'muted', cancelled: 'bad' }
const transportLabel: any = { flight: L('طيران','Flight'), bus: L('حافلة','Bus'), private: L('خاص','Private'), mixed: L('مختلط','Mixed') }
const catLabel: any = { flights: L('طيران','Flights'), hotel: L('فنادق','Hotels'), transport: L('نقل','Transport'), visa: L('تأشيرات','Visas'), food: L('إعاشة','Food'), guide: L('مرشد','Guide'), other: L('أخرى','Other') }
const travStatus: any = { registered: 'مسجّل', confirmed: 'مؤكد', cancelled: 'ملغى' }
const canManage = computed(() => auth.can('travel.manage'))
const trip = computed(() => view.value?.trip)
const isHoly = computed(() => trip.value && (trip.value.type === 'umrah' || trip.value.type === 'hajj'))

async function load() {
  view.value = await api.get(`/app/travel/trips/${id}`)
  Object.assign(form, tripToForm(view.value.trip))
  Object.assign(trav, { name: '', phone: '', passport_no: '', nationality: '', gender: '', package_id: view.value.trip.package_id || '', price: '' })
}
onMounted(async () => { await load(); packages.value = await api.get('/app/travel/packages') })

async function save() {
  err.value = ''; msg.value = ''; saving.value = true
  try { await api.patch(`/app/travel/trips/${id}`, tripPayload(form)); msg.value = tt('تم حفظ التعديلات','Changes saved'); editing.value = false; await load() }
  catch (e: any) { err.value = e.message } finally { saving.value = false }
}
async function setStatus(s: string) {
  try { await api.patch(`/app/travel/trips/${id}`, { ...tripPayload(tripToForm(trip.value)), status: s }); await load() }
  catch (e: any) { alert(e.message) }
}
async function addTraveler() {
  try { await api.post('/app/travel/travelers', { ...trav, trip_id: id, package_id: trav.package_id || null, price: trav.price === '' ? null : trav.price, gender: trav.gender || null }); await load() }
  catch (e: any) { alert(e.message) }
}
async function addExpense() {
  try { await api.post(`/app/travel/trips/${id}/expenses`, exp); exp.amount = 0; exp.note = ''; await load() }
  catch (e: any) { alert(e.message) }
}
async function delExpense(e: any) { if (!confirm(tt('حذف المصروف؟','Delete expense?'))) return; await api.del(`/app/travel/trips/${id}/expenses/${e.id}`); await load() }
async function remove() {
  if (!confirm(tt('حذف الرحلة؟','Delete trip?'))) return
  try { await api.del(`/app/travel/trips/${id}`); navigateTo('/travel/trips') } catch (e: any) { alert(e.message) }
}
</script>

<template>
  <div v-if="trip">
    <div class="breadcrumb"><NuxtLink to="/travel/trips">{{ tt('الرحلات','Trips') }}</NuxtLink> › <b>{{ trip.name }}</b></div>
    <div v-if="msg" class="alert ok">{{ msg }}</div>
    <div v-if="err" class="alert bad">{{ err }}</div>

    <!-- Edit mode -->
    <div v-if="editing" class="card">
      <TripForm :form="form" :packages="packages" />
      <div class="row" style="gap:10px;margin-top:18px;border-top:1px solid var(--line);padding-top:16px">
        <button class="btn" :disabled="saving" @click="save"><i class="ti ti-check" /> {{ tt('حفظ التعديلات','Save changes') }}</button>
        <button class="btn ghost" :disabled="saving" @click="editing = false; load()">{{ tt('إلغاء','Cancel') }}</button>
      </div>
    </div>

    <template v-else>
      <!-- Stats -->
      <div class="grid grid-4" style="margin-bottom:16px">
        <div class="card stat"><div class="ic" style="background:var(--info)"><i class="ti ti-users" /></div><div><b>{{ view.stats.travelers }}</b><small>{{ tt('مسافر','travelers') }} · {{ tt('متبقي','left') }} {{ view.stats.seats_left }} {{ tt('مقعد','seats') }}</small></div></div>
        <div class="card stat"><div class="ic" style="background:var(--ok)"><i class="ti ti-cash" /></div><div><b>{{ f.money(view.stats.collected) }}</b><small>{{ tt('محصّل من','collected of') }} {{ f.money(view.stats.revenue) }}</small></div></div>
        <div class="card stat"><div class="ic" style="background:var(--primary)"><i class="ti ti-receipt" /></div><div><b>{{ f.money(view.stats.expenses) }}</b><small>{{ tt('مصروفات','Expenses') }}</small></div></div>
        <div class="card stat"><div class="ic" style="background:var(--warn)"><i class="ti ti-trending-up" /></div><div><b>{{ f.money(view.stats.profit) }}</b><small>{{ tt('الربح المتوقع','Expected profit') }}</small></div></div>
      </div>

      <!-- Header + actions -->
      <div class="card">
        <div class="row between" style="align-items:flex-start">
          <div>
            <div class="row" style="gap:10px;align-items:center">
              <h2 style="margin:0">{{ trip.name }}</h2>
              <span class="badge" :class="trip.type === 'hajj' ? 'bad' : trip.type === 'umrah' ? 'ok' : 'info'">{{ typeLabel[trip.type] }}</span>
              <span class="badge" :class="stClass[trip.status]">{{ stLabel[trip.status] }}</span>
            </div>
            <p class="muted" style="margin:6px 0 0">
              {{ f.d(trip.departure_date) }} ← {{ f.d(trip.return_date) }}
              <span v-if="trip.destination"> · {{ trip.destination }}</span>
              <span v-if="trip.code"> · {{ tt('كود','code') }} {{ trip.code }}</span>
            </p>
          </div>
          <div v-if="canManage" class="row" style="gap:8px">
            <button v-if="trip.status === 'scheduled'" class="btn ok sm" @click="setStatus('ongoing')"><i class="ti ti-player-play" /> {{ tt('بدء الرحلة','Start trip') }}</button>
            <button v-if="trip.status === 'ongoing'" class="btn ok sm" @click="setStatus('completed')"><i class="ti ti-check" /> {{ tt('إكمال','Complete') }}</button>
            <button class="btn ghost sm" @click="editing = true"><i class="ti ti-pencil" /> {{ tt('تعديل','Edit') }}</button>
            <button class="btn ghost sm" style="color:var(--primary)" @click="remove"><i class="ti ti-trash" /></button>
          </div>
        </div>

        <!-- Trip info grid -->
        <hr style="border:none;border-top:1px solid var(--line);margin:14px 0">
        <div class="grid grid-4">
          <div><small class="muted">{{ tt('وسيلة النقل','Transport') }}</small><br /><b>{{ trip.transport ? transportLabel[trip.transport] : '—' }}</b></div>
          <div><small class="muted">{{ tt('شركة الطيران','Airline') }}</small><br /><b>{{ trip.airline || '—' }}</b></div>
          <div><small class="muted">{{ tt('رقم الرحلة الجوية','Flight no.') }}</small><br /><b dir="ltr">{{ trip.flight_no || '—' }}</b></div>
          <div><small class="muted">{{ tt('نقطة التجمّع','Boarding point') }}</small><br /><b>{{ trip.boarding_point || '—' }}</b></div>
          <template v-if="isHoly">
            <div><small class="muted">{{ tt('فندق مكة','Makkah hotel') }}</small><br /><b>{{ trip.hotel_makkah || '—' }}<span v-if="trip.nights_makkah" class="muted"> · {{ trip.nights_makkah }} {{ tt('ليالٍ','nights') }}</span></b></div>
            <div><small class="muted">{{ tt('فندق المدينة','Madinah hotel') }}</small><br /><b>{{ trip.hotel_madinah || '—' }}<span v-if="trip.nights_madinah" class="muted"> · {{ trip.nights_madinah }} {{ tt('ليالٍ','nights') }}</span></b></div>
          </template>
          <div><small class="muted">{{ tt('مشرف الرحلة','Supervisor') }}</small><br /><b>{{ trip.supervisor || '—' }}<span v-if="trip.supervisor_phone" class="muted" dir="ltr"> · {{ trip.supervisor_phone }}</span></b></div>
          <div><small class="muted">{{ tt('الطاقة / الباقة','Capacity / package') }}</small><br /><b>{{ trip.capacity }} {{ tt('مقعد','seats') }}<span v-if="trip.package" class="muted"> · {{ trip.package.name }}</span></b></div>
          <div v-if="trip.note" style="grid-column:1/-1"><small class="muted">{{ tt('ملاحظات','Notes') }}</small><br /><b>{{ trip.note }}</b></div>
        </div>
      </div>

      <!-- Travelers -->
      <div class="card">
        <div class="card-title"><i class="ti ti-users" /> {{ tt('المسافرون','Travelers') }}</div>
        <table class="table">
          <thead><tr><th>{{ tt('الاسم','Name') }}</th><th>{{ tt('الهاتف','Phone') }}</th><th>{{ tt('الجواز','Passport') }}</th><th>{{ tt('الجنسية','Nationality') }}</th><th>{{ tt('الباقة','Package') }}</th><th>{{ tt('السعر','Price') }}</th><th>{{ tt('المدفوع','Paid') }}</th><th>{{ tt('الحالة','Status') }}</th></tr></thead>
          <tbody>
            <tr v-for="t in trip.travelers" :key="t.id">
              <td><NuxtLink :to="`/travel/travelers?open=${t.id}`" style="color:var(--info)">{{ t.name }}</NuxtLink></td>
              <td dir="ltr" style="text-align:right">{{ t.phone || '—' }}</td>
              <td dir="ltr" style="text-align:right">{{ t.passport_no || '—' }}</td>
              <td>{{ t.nationality || '—' }}</td>
              <td>{{ t.package?.name || '—' }}</td>
              <td class="num">{{ f.money(t.price) }}</td>
              <td class="num" :style="Number(t.paid) < Number(t.price) ? 'color:var(--primary)' : 'color:var(--ok)'">{{ f.money(t.paid) }}</td>
              <td><span class="badge" :class="t.status === 'confirmed' ? 'ok' : t.status === 'cancelled' ? 'bad' : 'warn'">{{ travStatus[t.status] }}</span></td>
            </tr>
          </tbody>
        </table>
        <p v-if="!trip.travelers.length" class="empty">{{ tt('لا يوجد مسافرون بعد.','No travelers yet.') }}</p>
        <div v-if="canManage && trip.status !== 'cancelled'" class="row mt" style="align-items:flex-end">
          <div class="field" style="margin:0;flex:1"><label>{{ tt('إضافة مسافر','Add traveler') }}</label><input v-model="trav.name" placeholder="الاسم" /></div>
          <div class="field" style="margin:0"><label>{{ tt('هاتف','Phone') }}</label><input v-model="trav.phone" dir="ltr" style="width:120px" /></div>
          <div class="field" style="margin:0"><label>{{ tt('جواز','Passport') }}</label><input v-model="trav.passport_no" dir="ltr" style="width:110px" /></div>
          <div class="field" style="margin:0"><label>{{ tt('الجنسية','Nationality') }}</label><input v-model="trav.nationality" style="width:100px" /></div>
          <div class="field" style="margin:0"><label>{{ tt('الجنس','Gender') }}</label><UiSelect v-model="trav.gender" style="width:90px"><option value="">—</option><option value="male">{{ tt('ذكر','Male') }}</option><option value="female">{{ tt('أنثى','Female') }}</option></UiSelect></div>
          <div class="field" style="margin:0"><label>{{ tt('باقة','Package') }}</label><UiSelect v-model="trav.package_id" style="width:130px"><option value="">—</option><option v-for="p in packages" :key="p.id" :value="p.id">{{ p.name }}</option></UiSelect></div>
          <div class="field" style="margin:0"><label>{{ tt('سعر','Price') }}</label><input v-model="trav.price" type="number" step="0.001" dir="ltr" style="width:100px" :placeholder="f.money(trip.price)" /></div>
          <button class="btn" :disabled="!trav.name" @click="addTraveler"><i class="ti ti-plus" /></button>
        </div>
      </div>

      <!-- Expenses -->
      <div class="card">
        <div class="card-title"><i class="ti ti-receipt" /> {{ tt('مصروفات الرحلة','Trip expenses') }}</div>
        <table class="table">
          <thead><tr><th>{{ tt('التاريخ','Date') }}</th><th>{{ tt('الفئة','Category') }}</th><th>{{ tt('ملاحظة','Note') }}</th><th>{{ tt('المبلغ','Amount') }}</th><th v-if="canManage"></th></tr></thead>
          <tbody>
            <tr v-for="e in trip.expenses" :key="e.id">
              <td>{{ f.d(e.date) }}</td><td>{{ catLabel[e.category] }}</td><td class="muted">{{ e.note }}</td><td class="num">{{ f.money(e.amount) }}</td>
              <td v-if="canManage"><button class="btn ghost sm icon" style="color:var(--primary)" @click="delExpense(e)"><i class="ti ti-x" /></button></td>
            </tr>
          </tbody>
        </table>
        <p v-if="!trip.expenses.length" class="empty">{{ tt('لا توجد مصروفات.','No expenses.') }}</p>
        <div v-if="canManage" class="row mt" style="align-items:flex-end">
          <div class="field" style="margin:0"><label>{{ tt('الفئة','Category') }}</label><UiSelect v-model="exp.category"><option v-for="(l, k) in catLabel" :key="k" :value="k">{{ l }}</option></UiSelect></div>
          <div class="field" style="margin:0"><label>{{ tt('المبلغ','Amount') }}</label><input v-model.number="exp.amount" type="number" step="0.001" dir="ltr" style="width:120px" /></div>
          <div class="field" style="margin:0;flex:1"><label>{{ tt('ملاحظة','Note') }}</label><input v-model="exp.note" /></div>
          <button class="btn ghost" :disabled="!exp.amount" @click="addExpense"><i class="ti ti-plus" /> {{ tt('مصروف','Expense') }}</button>
        </div>
      </div>
    </template>
  </div>
</template>
