<script setup lang="ts">
definePageMeta({ title: 'المسافرون', titleEn: 'Travelers' })
const api = useApi(); const auth = useAuth(); const f = useFmt(); const { tt } = useI18n(); const route = useRoute()
const page = ref<any>(null); const trips = ref<any[]>([]); const packages = ref<any[]>([])
const filt = reactive({ q: '', trip_id: '', status: '', unpaid: false })
const view = ref<any>(null); const editing = ref(false); const err = ref('')
const form = reactive<any>({})
const pay = reactive({ amount: 0, method: 'cash', note: '' })
const stLabel: any = { registered: L('مسجّل','Registered'), confirmed: L('مؤكد','Confirmed'), cancelled: L('ملغى','Cancelled') }
const stClass: any = { registered: 'warn', confirmed: 'ok', cancelled: 'bad' }

async function load() { page.value = await api.get('/app/travel/travelers', { ...filt, unpaid: filt.unpaid ? 1 : '' }) }
onMounted(async () => { await load(); trips.value = await api.get('/app/travel/trips', { all: 1 }); packages.value = await api.get('/app/travel/packages'); if (route.query.open) show(Number(route.query.open)) })
async function show(id: number) { view.value = await api.get(`/app/travel/travelers/${id}`); editing.value = false; pay.amount = Math.max(0, Number(view.value.price) - Number(view.value.paid)) }
function startEdit() { const t = view.value; Object.assign(form, { name: t.name, phone: t.phone || '', passport_no: t.passport_no || '', passport_expiry: t.passport_expiry || '', nationality: t.nationality || '', gender: t.gender || '', birth_date: t.birth_date || '', package_id: t.package_id || '', price: +t.price, status: t.status, notes: t.notes || '' }); editing.value = true; err.value = '' }
async function save() { err.value = ''; try { await api.patch(`/app/travel/travelers/${view.value.id}`, { ...form, package_id: form.package_id || null, gender: form.gender || null, passport_expiry: form.passport_expiry || null, birth_date: form.birth_date || null }); await show(view.value.id); await load() } catch (e: any) { err.value = e.message } }
async function doPay() { try { await api.post(`/app/travel/travelers/${view.value.id}/pay`, pay); await show(view.value.id); await load() } catch (e: any) { alert(e.message) } }
async function remove() { if (!confirm(tt('حذف/إلغاء المسافر؟','Delete/cancel traveler?'))) return; try { await api.del(`/app/travel/travelers/${view.value.id}`); view.value = null; await load() } catch (e: any) { alert(e.message) } }
</script>

<template>
  <div>
    <div class="breadcrumb"><NuxtLink to="/travel">{{ tt('الحج والعمرة والسفر','Hajj, Umrah & Travel') }}</NuxtLink> › <b>{{ tt('المسافرون','Travelers') }}</b></div>
    <div class="card">
      <div class="row" style="margin-bottom:14px">
        <input v-model="filt.q" :placeholder="tt('اسم / هاتف / جواز','Name / phone / passport')" style="padding:8px 12px;border:1px solid var(--line);border-radius:10px" @keyup.enter="load" />
        <UiSelect v-model="filt.trip_id" style="padding:8px 12px;border:1px solid var(--line);border-radius:10px" @change="load"><option value="">كل الرحلات</option><option v-for="t in trips" :key="t.id" :value="t.id">{{ t.name }}</option></UiSelect>
        <UiSelect v-model="filt.status" style="padding:8px 12px;border:1px solid var(--line);border-radius:10px" @change="load"><option value="">كل الحالات</option><option v-for="(l, k) in stLabel" :key="k" :value="k">{{ l }}</option></UiSelect>
        <label class="switch"><input v-model="filt.unpaid" type="checkbox" @change="load" /><span /> {{ tt('لم يسددوا','Unpaid') }}</label>
        <button class="btn ghost" @click="load"><i class="ti ti-search" /></button>
        <NuxtLink to="/travel/trips" class="btn ghost" style="margin-right:auto">{{ tt('إضافة مسافر من صفحة الرحلة ←','Add traveler from the trip page ←') }}</NuxtLink>
      </div>
      <table class="table">
        <thead><tr><th>{{ tt('الاسم','Name') }}</th><th>{{ tt('الهاتف','Phone') }}</th><th>{{ tt('الجواز','Passport') }}</th><th>{{ tt('الرحلة','Trip') }}</th><th>{{ tt('الباقة','Package') }}</th><th>{{ tt('السعر','Price') }}</th><th>{{ tt('المدفوع','Paid') }}</th><th>{{ tt('المتبقي','Balance') }}</th><th>{{ tt('الحالة','Status') }}</th><th></th></tr></thead>
        <tbody>
          <tr v-for="t in page?.data" :key="t.id">
            <td><a href="#" style="color:var(--info);font-weight:700" @click.prevent="show(t.id)">{{ t.name }}</a></td><td dir="ltr" style="text-align:right">{{ t.phone || '—' }}</td><td dir="ltr" style="text-align:right">{{ t.passport_no || '—' }}</td>
            <td>{{ t.trip?.name }}<br /><small class="muted">{{ f.d(t.trip?.departure_date) }}</small></td><td>{{ t.package?.name || '—' }}</td>
            <td class="num">{{ f.money(t.price) }}</td><td class="num" style="color:var(--ok)">{{ f.money(t.paid) }}</td><td class="num" style="color:var(--primary)">{{ f.money(Number(t.price) - Number(t.paid)) }}</td>
            <td><span class="badge" :class="stClass[t.status]">{{ stLabel[t.status] }}</span></td><td><button class="btn ghost sm icon" @click="show(t.id)"><i class="ti ti-eye" /></button></td>
          </tr>
        </tbody>
      </table>
      <p v-if="page && !page.data.length" class="empty">{{ tt('لا يوجد مسافرون.','No travelers.') }}</p>
    </div>

    <UiModal v-if="view" :title="view.name" lg @close="view = null">
      <div v-if="err" class="alert bad">{{ err }}</div>
      <template v-if="!editing">
        <div class="row" style="gap:20px;flex-wrap:wrap;margin-bottom:10px">
          <div><small class="muted">{{ tt('الرحلة','Trip') }}</small><br /><b>{{ view.trip?.name }}</b> <small>{{ f.d(view.trip?.departure_date) }}</small></div>
          <div><small class="muted">{{ tt('الهاتف','Phone') }}</small><br /><b dir="ltr">{{ view.phone || '—' }}</b></div>
          <div><small class="muted">{{ tt('الجواز','Passport') }}</small><br /><b dir="ltr">{{ view.passport_no || '—' }}</b> <small>{{ view.passport_expiry ? `ينتهي ${f.d(view.passport_expiry)}` : '' }}</small></div>
          <div><small class="muted">{{ tt('الجنسية','Nationality') }}</small><br /><b>{{ view.nationality || '—' }}</b></div>
          <div><small class="muted">{{ tt('الباقة','Package') }}</small><br /><b>{{ view.package?.name || '—' }}</b></div>
          <div><small class="muted">{{ tt('الحالة','Status') }}</small><br /><span class="badge" :class="stClass[view.status]">{{ stLabel[view.status] }}</span></div>
        </div>
        <div class="inv-totals"><div class="row between"><span>{{ tt('السعر','Price') }}</span><b>{{ f.money(view.price, true) }}</b></div><div class="row between"><span>{{ tt('المدفوع','Paid') }}</span><b style="color:var(--ok)">{{ f.money(view.paid) }}</b></div><div class="row between"><span>{{ tt('المتبقي','Balance') }}</span><b style="color:var(--primary)">{{ f.money(Number(view.price) - Number(view.paid)) }}</b></div></div>
        <table v-if="view.payments?.length" class="table mt"><tbody><tr v-for="p in view.payments" :key="p.id"><td>{{ f.d(p.date) }}</td><td>{{ f.methodLabel(p.method) }}</td><td class="num">{{ f.money(p.amount) }}</td><td class="muted">{{ p.note }}</td></tr></tbody></table>
        <p v-if="view.notes" class="muted mt">{{ view.notes }}</p>
      </template>
      <template v-else>
        <div class="grid grid-3">
          <div class="field"><label>{{ tt('الاسم','Name') }}</label><input v-model="form.name" /></div><div class="field"><label>{{ tt('الهاتف','Phone') }}</label><input v-model="form.phone" dir="ltr" /></div><div class="field"><label>{{ tt('الجنس','Gender') }}</label><UiSelect v-model="form.gender"><option value="">—</option><option value="male">{{ tt('ذكر','Male') }}</option><option value="female">{{ tt('أنثى','Female') }}</option></UiSelect></div>
          <div class="field"><label>{{ tt('رقم الجواز','Passport no.') }}</label><input v-model="form.passport_no" dir="ltr" /></div><div class="field"><label>{{ tt('انتهاء الجواز','Passport expiry') }}</label><input v-model="form.passport_expiry" type="date" /></div><div class="field"><label>{{ tt('الجنسية','Nationality') }}</label><input v-model="form.nationality" /></div>
          <div class="field"><label>{{ tt('تاريخ الميلاد','Date of birth') }}</label><input v-model="form.birth_date" type="date" /></div><div class="field"><label>{{ tt('الباقة','Package') }}</label><UiSelect v-model="form.package_id"><option value="">—</option><option v-for="p in packages" :key="p.id" :value="p.id">{{ p.name }}</option></UiSelect></div><div class="field"><label>{{ tt('السعر','Price') }}</label><input v-model.number="form.price" type="number" step="0.001" dir="ltr" /></div>
          <div class="field"><label>{{ tt('الحالة','Status') }}</label><UiSelect v-model="form.status"><option v-for="(l, k) in stLabel" :key="k" :value="k">{{ l }}</option></UiSelect></div>
        </div>
        <div class="field"><label>{{ tt('ملاحظات','Notes') }}</label><textarea v-model="form.notes" rows="2" /></div>
      </template>
      <template #footer>
        <div class="row" style="width:100%;justify-content:space-between;flex-wrap:wrap">
          <div class="row">
            <template v-if="!editing"><button class="btn ghost" @click="startEdit">{{ tt('تعديل','Edit') }}</button><button v-if="auth.can('travel.manage')" class="btn ghost" style="color:var(--primary)" @click="remove">{{ tt('حذف/إلغاء','Delete/cancel') }}</button></template>
            <template v-else><button class="btn ghost" @click="editing = false">{{ tt('رجوع','Back') }}</button><button class="btn" @click="save">{{ tt('حفظ','Save') }}</button></template>
          </div>
          <div v-if="!editing && view.status !== 'cancelled' && Number(view.price) - Number(view.paid) > 0" class="row">
            <input v-model.number="pay.amount" type="number" step="0.001" dir="ltr" style="width:110px;padding:7px;border:1px solid var(--line);border-radius:8px" />
            <UiSelect v-model="pay.method" style="padding:7px;border:1px solid var(--line);border-radius:8px"><option value="cash">{{ tt('نقدي','Cash') }}</option><option value="card">{{ tt('بطاقة','Card') }}</option><option value="transfer">{{ tt('تحويل','Transfer') }}</option></UiSelect>
            <button class="btn info" @click="doPay">{{ tt('تسجيل دفعة','Add payment') }}</button>
          </div>
        </div>
      </template>
    </UiModal>
  </div>
</template>
