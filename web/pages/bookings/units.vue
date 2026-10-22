<script setup lang="ts">
definePageMeta({ title: 'القاعات / الغرف / الوحدات', titleEn: 'Halls / Rooms / Units' })
const api = useApi(); const auth = useAuth(); const f = useFmt(); const { tt } = useI18n()
const rows = ref<any[]>([]); const modal = ref(false); const editing = ref<any>(null); const err = ref('')
const blank = () => ({ name: '', name_en: '', type: 'hall', capacity: '', price_day: 0, price_night: 0, price_hour: 0, deposit: 0, location: '', color: '#e11d48', description: '', is_active: true })
const form = reactive<any>(blank())
const typeLabel: any = { hall: tt('قاعة','Hall'), chalet: tt('شاليه','Chalet'), room: tt('غرفة','Room'), other: tt('أخرى','Other') }
async function load() { rows.value = await api.get('/app/bookings/units') }
onMounted(load)
function openNew() { editing.value = null; Object.assign(form, blank()); err.value = ''; modal.value = true }
function openEdit(u: any) { editing.value = u; Object.assign(form, { ...blank(), ...u, name_en: u.name_en || '', capacity: u.capacity || '', location: u.location || '', description: u.description || '', price_day: +u.price_day, price_night: +u.price_night, price_hour: +u.price_hour, deposit: +u.deposit }); err.value = ''; modal.value = true }
async function save() { err.value = ''; try { const b = { ...form, capacity: form.capacity || null }; editing.value ? await api.patch(`/app/bookings/units/${editing.value.id}`, b) : await api.post('/app/bookings/units', b); modal.value = false; await load() } catch (e: any) { err.value = e.message } }
async function remove(u: any) { if (!confirm(tt(`حذف ${u.name}؟`,`Delete ${u.name}?`))) return; try { await api.del(`/app/bookings/units/${u.id}`); await load() } catch (e: any) { alert(e.message) } }
</script>

<template>
  <div>
    <div class="breadcrumb"><NuxtLink to="/bookings">{{ tt('الحجوزات','Bookings') }}</NuxtLink> › <b>{{ tt('الوحدات','Units') }}</b></div>
    <div class="card">
      <div class="row end" style="margin-bottom:12px"><button v-if="auth.can('bookings.manage')" class="btn" @click="openNew"><i class="ti ti-plus" /> {{ tt('وحدة جديدة','New unit') }}</button></div>
      <table class="table">
        <thead><tr><th>{{ tt('الوحدة','Unit') }}</th><th>{{ tt('النوع','Type') }}</th><th>{{ tt('السعة','Capacity') }}</th><th>{{ tt('يوم','Day') }}</th><th>{{ tt('ليلة','Night') }}</th><th>{{ tt('ساعة','Hour') }}</th><th>{{ tt('تأمين','Deposit') }}</th><th>{{ tt('حجوزات قادمة','Upcoming') }}</th><th>{{ tt('الحالة','Status') }}</th><th></th></tr></thead>
        <tbody>
          <tr v-for="u in rows" :key="u.id">
            <td><span :style="`display:inline-block;width:12px;height:12px;border-radius:50%;background:${u.color};margin-left:6px`" /><b>{{ u.name }}</b><br /><small class="muted">{{ u.location }}</small></td>
            <td>{{ typeLabel[u.type] }}</td><td>{{ u.capacity || '—' }}</td><td class="num">{{ f.money(u.price_day) }}</td><td class="num">{{ f.money(u.price_night) }}</td><td class="num">{{ f.money(u.price_hour) }}</td><td class="num">{{ f.money(u.deposit) }}</td>
            <td>{{ u.upcoming_count }}</td><td><span class="badge" :class="u.is_active ? 'ok' : 'muted'">{{ u.is_active ? tt('نشطة','Active') : tt('موقوفة','Inactive') }}</span></td>
            <td v-if="auth.can('bookings.manage')" class="row" style="gap:6px"><button class="btn ghost sm icon" @click="openEdit(u)"><i class="ti ti-pencil" /></button><button class="btn ghost sm icon" style="color:var(--primary)" @click="remove(u)"><i class="ti ti-trash" /></button></td>
          </tr>
        </tbody>
      </table>
      <p v-if="!rows.length" class="empty">{{ tt('أضف قاعاتك / شاليهاتك / غرفك لتبدأ الحجز.','Add your halls / chalets / rooms to start booking.') }}</p>
    </div>
    <UiModal v-if="modal" :title="editing ? tt('تعديل وحدة','Edit unit') : tt('وحدة جديدة','New unit')" lg @close="modal = false">
      <div v-if="err" class="alert bad">{{ err }}</div>
      <div class="grid grid-3">
        <div class="field"><label>{{ tt('الاسم','Name') }}</label><input v-model="form.name" /></div>
        <div class="field"><label>{{ tt('الاسم (إنجليزي)','Name (English)') }}</label><input v-model="form.name_en" dir="ltr" /></div>
        <div class="field"><label>{{ tt('النوع','Type') }}</label><select v-model="form.type"><option v-for="(l, k) in typeLabel" :key="k" :value="k">{{ l }}</option></select></div>
        <div class="field"><label>{{ tt('السعة (أشخاص)','Capacity (people)') }}</label><input v-model.number="form.capacity" type="number" dir="ltr" /></div>
        <div class="field"><label>{{ tt('سعر اليوم الكامل','Full-day price') }}</label><input v-model.number="form.price_day" type="number" step="0.001" dir="ltr" /></div>
        <div class="field"><label>{{ tt('سعر الليلة','Night price') }}</label><input v-model.number="form.price_night" type="number" step="0.001" dir="ltr" /></div>
        <div class="field"><label>{{ tt('سعر الساعة','Hour price') }}</label><input v-model.number="form.price_hour" type="number" step="0.001" dir="ltr" /></div>
        <div class="field"><label>{{ tt('التأمين الافتراضي','Default deposit') }}</label><input v-model.number="form.deposit" type="number" step="0.001" dir="ltr" /></div>
        <div class="field"><label>{{ tt('اللون في التقويم','Calendar color') }}</label><input v-model="form.color" type="color" style="height:40px" /></div>
      </div>
      <div class="field"><label>{{ tt('الموقع','Location') }}</label><input v-model="form.location" /></div>
      <div class="field"><label>{{ tt('الوصف','Description') }}</label><textarea v-model="form.description" rows="2" /></div>
      <label class="switch"><input v-model="form.is_active" type="checkbox" /><span /> {{ tt('نشطة','Active') }}</label>
      <template #footer><button class="btn ghost" @click="modal = false">{{ tt('إلغاء','Cancel') }}</button><button class="btn" @click="save">{{ tt('حفظ','Save') }}</button></template>
    </UiModal>
  </div>
</template>
