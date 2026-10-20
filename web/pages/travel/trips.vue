<script setup lang="ts">
definePageMeta({ title: 'الرحلات' })
const api = useApi(); const auth = useAuth(); const f = useFmt(); const route = useRoute()
const page = ref<any>(null); const packages = ref<any[]>([])
const filt = reactive({ q: '', type: '', status: '' })
const modal = ref(false); const editing = ref<any>(null); const err = ref('')
const blank = () => ({ name: '', type: 'umrah', destination: '', departure_date: f.today(), return_date: '', capacity: 40, price: 0, package_id: '', status: 'scheduled', note: '' })
const form = reactive<any>(blank())
const view = ref<any>(null)
const trav = reactive<any>({ name: '', phone: '', passport_no: '', nationality: '', gender: '', package_id: '', price: '' })
const exp = reactive({ category: 'flights', amount: 0, note: '' })
const typeLabel: any = { umrah: 'عمرة', hajj: 'حج', tourism: 'سياحة' }
const stLabel: any = { scheduled: 'مجدولة', ongoing: 'جارية', completed: 'مكتملة', cancelled: 'ملغاة' }
const stClass: any = { scheduled: 'info', ongoing: 'ok', completed: 'muted', cancelled: 'bad' }
const catLabel: any = { flights: 'طيران', hotel: 'فنادق', transport: 'نقل', visa: 'تأشيرات', food: 'إعاشة', guide: 'مرشد', other: 'أخرى' }
const canManage = computed(() => auth.can('travel.manage'))

async function load() { page.value = await api.get('/app/travel/trips', filt) }
onMounted(async () => { await load(); packages.value = await api.get('/app/travel/packages'); if (route.query.open) show(Number(route.query.open)) })
function openNew() { editing.value = null; Object.assign(form, blank()); err.value = ''; modal.value = true }
function openEdit(t: any) { editing.value = t; Object.assign(form, { ...blank(), ...t, destination: t.destination || '', return_date: t.return_date || '', package_id: t.package_id || '', note: t.note || '', price: +t.price }); err.value = ''; modal.value = true }
async function save() { err.value = ''; try { const b = { ...form, package_id: form.package_id || null, return_date: form.return_date || null }; editing.value ? await api.patch(`/app/travel/trips/${editing.value.id}`, b) : await api.post('/app/travel/trips', b); modal.value = false; await load(); if (view.value) show(view.value.trip.id) } catch (e: any) { err.value = e.message } }
async function show(id: number) { view.value = await api.get(`/app/travel/trips/${id}`); Object.assign(trav, { name: '', phone: '', passport_no: '', nationality: '', gender: '', package_id: view.value.trip.package_id || '', price: '' }) }
async function addTraveler() { try { await api.post('/app/travel/travelers', { ...trav, trip_id: view.value.trip.id, package_id: trav.package_id || null, price: trav.price === '' ? null : trav.price, gender: trav.gender || null }); await show(view.value.trip.id); await load() } catch (e: any) { alert(e.message) } }
async function addExpense() { try { await api.post(`/app/travel/trips/${view.value.trip.id}/expenses`, exp); exp.amount = 0; exp.note = ''; await show(view.value.trip.id); await load() } catch (e: any) { alert(e.message) } }
async function delExpense(e: any) { if (!confirm('حذف المصروف؟')) return; await api.del(`/app/travel/trips/${view.value.trip.id}/expenses/${e.id}`); await show(view.value.trip.id) }
async function setStatus(s: string) { try { await api.patch(`/app/travel/trips/${view.value.trip.id}`, { ...view.value.trip, status: s }); await show(view.value.trip.id); await load() } catch (e: any) { alert(e.message) } }
</script>

<template>
  <div>
    <div class="breadcrumb"><NuxtLink to="/travel">الحج والعمرة والسفر</NuxtLink> › <b>الرحلات</b></div>
    <div class="card">
      <div class="row between" style="margin-bottom:14px">
        <div class="row">
          <select v-model="filt.type" style="padding:8px 12px;border:1px solid var(--line);border-radius:10px" @change="load"><option value="">جميع الأنواع</option><option v-for="(l, k) in typeLabel" :key="k" :value="k">{{ l }}</option></select>
          <select v-model="filt.status" style="padding:8px 12px;border:1px solid var(--line);border-radius:10px" @change="load"><option value="">جميع الحالات</option><option v-for="(l, k) in stLabel" :key="k" :value="k">{{ l }}</option></select>
          <input v-model="filt.q" placeholder="البحث بالاسم أو الوجهة..." style="padding:8px 12px;border:1px solid var(--line);border-radius:10px" @keyup.enter="load" /><button class="btn ghost" @click="load"><i class="ti ti-search" /></button>
        </div>
        <button v-if="canManage" class="btn" @click="openNew"><i class="ti ti-plus" /> إضافة رحلة</button>
      </div>
      <table class="table">
        <thead><tr><th>#</th><th>الاسم</th><th>النوع</th><th>الحالة</th><th>المغادرة</th><th>الإرجاع</th><th>الوجهة</th><th>الطاقة</th><th>المسافرون</th><th>الإيراد</th><th>المحصّل</th><th>المصروفات</th><th></th></tr></thead>
        <tbody>
          <tr v-for="(t, i) in page?.data" :key="t.id">
            <td>{{ i + 1 }}</td><td><a href="#" style="color:var(--info);font-weight:700" @click.prevent="show(t.id)">{{ t.name }}</a></td><td><span class="badge" :class="t.type === 'hajj' ? 'bad' : t.type === 'umrah' ? 'ok' : 'info'">{{ typeLabel[t.type] }}</span></td><td><span class="badge" :class="stClass[t.status]">{{ stLabel[t.status] }}</span></td>
            <td>{{ f.d(t.departure_date) }}</td><td>{{ f.d(t.return_date) }}</td><td>{{ t.destination }}</td><td>{{ t.capacity }}</td><td><span class="badge" :class="t.capacity && t.travelers_count >= t.capacity ? 'bad' : 'muted'">{{ t.travelers_count }}</span></td>
            <td class="num">{{ f.money(t.revenue) }}</td><td class="num" style="color:var(--ok)">{{ f.money(t.collected) }}</td><td class="num" style="color:var(--primary)">{{ f.money(t.expenses_total) }}</td>
            <td><button class="btn ghost sm icon" @click="show(t.id)"><i class="ti ti-eye" /></button></td>
          </tr>
        </tbody>
      </table>
      <p v-if="page && !page.data.length" class="empty">لا توجد رحلات.</p>
    </div>

    <UiModal v-if="modal" :title="editing ? 'تعديل رحلة' : 'رحلة جديدة'" lg @close="modal = false">
      <div v-if="err" class="alert bad">{{ err }}</div>
      <div class="grid grid-3">
        <div class="field"><label>اسم الرحلة</label><input v-model="form.name" /></div>
        <div class="field"><label>النوع</label><select v-model="form.type"><option v-for="(l, k) in typeLabel" :key="k" :value="k">{{ l }}</option></select></div>
        <div class="field"><label>الوجهة</label><input v-model="form.destination" /></div>
        <div class="field"><label>المغادرة</label><input v-model="form.departure_date" type="date" /></div>
        <div class="field"><label>العودة</label><input v-model="form.return_date" type="date" /></div>
        <div class="field"><label>الطاقة الاستيعابية</label><input v-model.number="form.capacity" type="number" dir="ltr" /></div>
        <div class="field"><label>السعر الافتراضي للمسافر</label><input v-model.number="form.price" type="number" step="0.001" dir="ltr" /></div>
        <div class="field"><label>الباقة الافتراضية</label><select v-model="form.package_id"><option value="">—</option><option v-for="p in packages" :key="p.id" :value="p.id">{{ p.name }} ({{ f.money(p.price) }})</option></select></div>
        <div class="field"><label>الحالة</label><select v-model="form.status"><option v-for="(l, k) in stLabel" :key="k" :value="k">{{ l }}</option></select></div>
      </div>
      <div class="field"><label>ملاحظات</label><textarea v-model="form.note" rows="2" /></div>
      <template #footer><button class="btn ghost" @click="modal = false">إلغاء</button><button class="btn" @click="save">حفظ</button></template>
    </UiModal>

    <UiModal v-if="view" :title="`${view.trip.name} — ${typeLabel[view.trip.type]}`" lg @close="view = null">
      <div class="grid grid-4" style="margin-bottom:12px">
        <div class="stat"><div class="ic" style="background:var(--info)"><i class="ti ti-users" /></div><div><b>{{ view.stats.travelers }}</b><small>مسافر · متبقي {{ view.stats.seats_left }} مقعد</small></div></div>
        <div class="stat"><div class="ic" style="background:var(--ok)"><i class="ti ti-cash" /></div><div><b>{{ f.money(view.stats.collected) }}</b><small>محصّل من {{ f.money(view.stats.revenue) }}</small></div></div>
        <div class="stat"><div class="ic" style="background:var(--primary)"><i class="ti ti-receipt" /></div><div><b>{{ f.money(view.stats.expenses) }}</b><small>مصروفات</small></div></div>
        <div class="stat"><div class="ic" style="background:var(--warn)"><i class="ti ti-trending-up" /></div><div><b>{{ f.money(view.stats.profit) }}</b><small>الربح المتوقع</small></div></div>
      </div>
      <div class="row" style="gap:16px;margin-bottom:10px"><span>{{ f.d(view.trip.departure_date) }} ← {{ f.d(view.trip.return_date) }}</span><span>{{ view.trip.destination }}</span><span class="badge" :class="stClass[view.trip.status]">{{ stLabel[view.trip.status] }}</span>
        <template v-if="canManage"><button class="btn ghost sm" @click="openEdit(view.trip)">تعديل</button><button v-if="view.trip.status === 'scheduled'" class="btn ghost sm" @click="setStatus('ongoing')">بدء الرحلة</button><button v-if="view.trip.status === 'ongoing'" class="btn ghost sm" @click="setStatus('completed')">إكمال</button></template></div>

      <div class="card-title"><i class="ti ti-users" /> المسافرون</div>
      <table class="table"><thead><tr><th>الاسم</th><th>الهاتف</th><th>الجواز</th><th>الباقة</th><th>السعر</th><th>المدفوع</th><th>الحالة</th></tr></thead>
        <tbody><tr v-for="t in view.trip.travelers" :key="t.id"><td><NuxtLink :to="`/travel/travelers?open=${t.id}`" style="color:var(--info)">{{ t.name }}</NuxtLink></td><td dir="ltr" style="text-align:right">{{ t.phone }}</td><td dir="ltr" style="text-align:right">{{ t.passport_no }}</td><td>{{ t.package?.name || '—' }}</td><td class="num">{{ f.money(t.price) }}</td><td class="num" :style="Number(t.paid) < Number(t.price) ? 'color:var(--primary)' : 'color:var(--ok)'">{{ f.money(t.paid) }}</td><td><span class="badge" :class="t.status === 'confirmed' ? 'ok' : t.status === 'cancelled' ? 'bad' : 'warn'">{{ ({ registered: 'مسجّل', confirmed: 'مؤكد', cancelled: 'ملغى' } as any)[t.status] }}</span></td></tr></tbody></table>
      <div v-if="view.trip.status !== 'cancelled'" class="row mt" style="align-items:flex-end">
        <div class="field" style="margin:0;flex:1"><label>إضافة مسافر</label><input v-model="trav.name" placeholder="الاسم" /></div>
        <div class="field" style="margin:0"><label>هاتف</label><input v-model="trav.phone" dir="ltr" style="width:130px" /></div>
        <div class="field" style="margin:0"><label>جواز</label><input v-model="trav.passport_no" dir="ltr" style="width:110px" /></div>
        <div class="field" style="margin:0"><label>باقة</label><select v-model="trav.package_id" style="width:140px"><option value="">—</option><option v-for="p in packages" :key="p.id" :value="p.id">{{ p.name }}</option></select></div>
        <div class="field" style="margin:0"><label>سعر</label><input v-model="trav.price" type="number" step="0.001" dir="ltr" style="width:100px" :placeholder="f.money(view.trip.price)" /></div>
        <button class="btn" :disabled="!trav.name" @click="addTraveler"><i class="ti ti-plus" /></button>
      </div>

      <div class="card-title mt"><i class="ti ti-receipt" /> مصروفات الرحلة</div>
      <table class="table"><tbody><tr v-for="e in view.trip.expenses" :key="e.id"><td>{{ f.d(e.date) }}</td><td>{{ catLabel[e.category] }}</td><td class="muted">{{ e.note }}</td><td class="num">{{ f.money(e.amount) }}</td><td v-if="canManage"><button class="btn ghost sm icon" style="color:var(--primary)" @click="delExpense(e)"><i class="ti ti-x" /></button></td></tr></tbody></table>
      <div v-if="canManage" class="row mt" style="align-items:flex-end">
        <div class="field" style="margin:0"><label>الفئة</label><select v-model="exp.category"><option v-for="(l, k) in catLabel" :key="k" :value="k">{{ l }}</option></select></div>
        <div class="field" style="margin:0"><label>المبلغ</label><input v-model.number="exp.amount" type="number" step="0.001" dir="ltr" style="width:120px" /></div>
        <div class="field" style="margin:0;flex:1"><label>ملاحظة</label><input v-model="exp.note" /></div>
        <button class="btn ghost" :disabled="!exp.amount" @click="addExpense"><i class="ti ti-plus" /> مصروف</button>
      </div>
    </UiModal>
  </div>
</template>
