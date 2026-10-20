<script setup lang="ts">
definePageMeta({ title: 'الخدمات الإضافية' })
const api = useApi(); const auth = useAuth(); const f = useFmt()
const rows = ref<any[]>([]); const units = ref<any[]>([]); const modal = ref(false); const editing = ref<any>(null); const err = ref('')
const form = reactive<any>({ name: '', name_en: '', price: 0, unit_id: '', is_active: true })
async function load() { rows.value = await api.get('/app/bookings/services'); units.value = await api.get('/app/bookings/units') }
onMounted(load)
function openNew() { editing.value = null; Object.assign(form, { name: '', name_en: '', price: 0, unit_id: '', is_active: true }); err.value = ''; modal.value = true }
function openEdit(s: any) { editing.value = s; Object.assign(form, { name: s.name, name_en: s.name_en || '', price: +s.price, unit_id: s.unit_id || '', is_active: s.is_active }); err.value = ''; modal.value = true }
async function save() { err.value = ''; try { const b = { ...form, unit_id: form.unit_id || null }; editing.value ? await api.patch(`/app/bookings/services/${editing.value.id}`, b) : await api.post('/app/bookings/services', b); modal.value = false; await load() } catch (e: any) { err.value = e.message } }
async function remove(s: any) { if (!confirm(`حذف ${s.name}؟`)) return; try { await api.del(`/app/bookings/services/${s.id}`); await load() } catch (e: any) { alert(e.message) } }
</script>

<template>
  <div>
    <div class="breadcrumb"><NuxtLink to="/bookings">الحجوزات</NuxtLink> › <b>الخدمات الإضافية</b></div>
    <div class="card">
      <div class="row end" style="margin-bottom:12px"><button v-if="auth.can('bookings.manage')" class="btn" @click="openNew"><i class="ti ti-plus" /> إضافة خدمة</button></div>
      <table class="table">
        <thead><tr><th>#</th><th>الاسم (عربي)</th><th>الاسم (إنجليزي)</th><th>الوحدة</th><th>السعر</th><th>الحالة</th><th></th></tr></thead>
        <tbody>
          <tr v-for="(s, i) in rows" :key="s.id">
            <td>{{ i + 1 }}</td><td><b>{{ s.name }}</b></td><td dir="ltr" style="text-align:right">{{ s.name_en || '—' }}</td><td>{{ s.unit?.name || 'كل الوحدات' }}</td><td class="num">{{ f.money(s.price) }}</td>
            <td><span class="badge" :class="s.is_active ? 'ok' : 'muted'">{{ s.is_active ? 'مفعّل' : 'غير نشط' }}</span></td>
            <td v-if="auth.can('bookings.manage')" class="row" style="gap:6px"><button class="btn ghost sm icon" @click="openEdit(s)"><i class="ti ti-pencil" /></button><button class="btn ghost sm icon" style="color:var(--primary)" @click="remove(s)"><i class="ti ti-trash" /></button></td>
          </tr>
        </tbody>
      </table>
      <p v-if="!rows.length" class="empty">لا توجد خدمات بعد (ضيافة، تصوير، كوشة، دي جي...).</p>
    </div>
    <UiModal v-if="modal" :title="editing ? 'تعديل خدمة' : 'خدمة جديدة'" @close="modal = false">
      <div v-if="err" class="alert bad">{{ err }}</div>
      <div class="field"><label>الاسم (عربي)</label><input v-model="form.name" /></div>
      <div class="field"><label>الاسم (إنجليزي)</label><input v-model="form.name_en" dir="ltr" /></div>
      <div class="field"><label>السعر</label><input v-model.number="form.price" type="number" step="0.001" dir="ltr" /></div>
      <div class="field"><label>الوحدة</label><select v-model="form.unit_id"><option value="">كل الوحدات</option><option v-for="u in units" :key="u.id" :value="u.id">{{ u.name }}</option></select></div>
      <label class="switch"><input v-model="form.is_active" type="checkbox" /><span /> مفعّل</label>
      <template #footer><button class="btn ghost" @click="modal = false">إلغاء</button><button class="btn" @click="save">حفظ</button></template>
    </UiModal>
  </div>
</template>
