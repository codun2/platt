<script setup lang="ts">
definePageMeta({ title: 'القاعات / الغرف / الوحدات' })
const api = useApi(); const auth = useAuth(); const f = useFmt()
const rows = ref<any[]>([]); const modal = ref(false); const editing = ref<any>(null); const err = ref('')
const blank = () => ({ name: '', name_en: '', type: 'hall', capacity: '', price_day: 0, price_night: 0, price_hour: 0, deposit: 0, location: '', color: '#e11d48', description: '', is_active: true })
const form = reactive<any>(blank())
const typeLabel: any = { hall: 'قاعة', chalet: 'شاليه', room: 'غرفة', other: 'أخرى' }
async function load() { rows.value = await api.get('/app/bookings/units') }
onMounted(load)
function openNew() { editing.value = null; Object.assign(form, blank()); err.value = ''; modal.value = true }
function openEdit(u: any) { editing.value = u; Object.assign(form, { ...blank(), ...u, name_en: u.name_en || '', capacity: u.capacity || '', location: u.location || '', description: u.description || '', price_day: +u.price_day, price_night: +u.price_night, price_hour: +u.price_hour, deposit: +u.deposit }); err.value = ''; modal.value = true }
async function save() { err.value = ''; try { const b = { ...form, capacity: form.capacity || null }; editing.value ? await api.patch(`/app/bookings/units/${editing.value.id}`, b) : await api.post('/app/bookings/units', b); modal.value = false; await load() } catch (e: any) { err.value = e.message } }
async function remove(u: any) { if (!confirm(`حذف ${u.name}؟`)) return; try { await api.del(`/app/bookings/units/${u.id}`); await load() } catch (e: any) { alert(e.message) } }
</script>

<template>
  <div>
    <div class="breadcrumb"><NuxtLink to="/bookings">الحجوزات</NuxtLink> › <b>الوحدات</b></div>
    <div class="card">
      <div class="row end" style="margin-bottom:12px"><button v-if="auth.can('bookings.manage')" class="btn" @click="openNew"><i class="ti ti-plus" /> وحدة جديدة</button></div>
      <table class="table">
        <thead><tr><th>الوحدة</th><th>النوع</th><th>السعة</th><th>يوم</th><th>ليلة</th><th>ساعة</th><th>تأمين</th><th>حجوزات قادمة</th><th>الحالة</th><th></th></tr></thead>
        <tbody>
          <tr v-for="u in rows" :key="u.id">
            <td><span :style="`display:inline-block;width:12px;height:12px;border-radius:50%;background:${u.color};margin-left:6px`" /><b>{{ u.name }}</b><br /><small class="muted">{{ u.location }}</small></td>
            <td>{{ typeLabel[u.type] }}</td><td>{{ u.capacity || '—' }}</td><td class="num">{{ f.money(u.price_day) }}</td><td class="num">{{ f.money(u.price_night) }}</td><td class="num">{{ f.money(u.price_hour) }}</td><td class="num">{{ f.money(u.deposit) }}</td>
            <td>{{ u.upcoming_count }}</td><td><span class="badge" :class="u.is_active ? 'ok' : 'muted'">{{ u.is_active ? 'نشطة' : 'موقوفة' }}</span></td>
            <td v-if="auth.can('bookings.manage')" class="row" style="gap:6px"><button class="btn ghost sm icon" @click="openEdit(u)"><i class="ti ti-pencil" /></button><button class="btn ghost sm icon" style="color:var(--primary)" @click="remove(u)"><i class="ti ti-trash" /></button></td>
          </tr>
        </tbody>
      </table>
      <p v-if="!rows.length" class="empty">أضف قاعاتك / شاليهاتك / غرفك لتبدأ الحجز.</p>
    </div>
    <UiModal v-if="modal" :title="editing ? 'تعديل وحدة' : 'وحدة جديدة'" lg @close="modal = false">
      <div v-if="err" class="alert bad">{{ err }}</div>
      <div class="grid grid-3">
        <div class="field"><label>الاسم</label><input v-model="form.name" /></div>
        <div class="field"><label>الاسم (إنجليزي)</label><input v-model="form.name_en" dir="ltr" /></div>
        <div class="field"><label>النوع</label><select v-model="form.type"><option v-for="(l, k) in typeLabel" :key="k" :value="k">{{ l }}</option></select></div>
        <div class="field"><label>السعة (أشخاص)</label><input v-model.number="form.capacity" type="number" dir="ltr" /></div>
        <div class="field"><label>سعر اليوم الكامل</label><input v-model.number="form.price_day" type="number" step="0.001" dir="ltr" /></div>
        <div class="field"><label>سعر الليلة</label><input v-model.number="form.price_night" type="number" step="0.001" dir="ltr" /></div>
        <div class="field"><label>سعر الساعة</label><input v-model.number="form.price_hour" type="number" step="0.001" dir="ltr" /></div>
        <div class="field"><label>التأمين الافتراضي</label><input v-model.number="form.deposit" type="number" step="0.001" dir="ltr" /></div>
        <div class="field"><label>اللون في التقويم</label><input v-model="form.color" type="color" style="height:40px" /></div>
      </div>
      <div class="field"><label>الموقع</label><input v-model="form.location" /></div>
      <div class="field"><label>الوصف</label><textarea v-model="form.description" rows="2" /></div>
      <label class="switch"><input v-model="form.is_active" type="checkbox" /><span /> نشطة</label>
      <template #footer><button class="btn ghost" @click="modal = false">إلغاء</button><button class="btn" @click="save">حفظ</button></template>
    </UiModal>
  </div>
</template>
