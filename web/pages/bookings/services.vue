<script setup lang="ts">
definePageMeta({ title: 'الخدمات الإضافية', titleEn: 'Add-on Services' })
const api = useApi(); const auth = useAuth(); const f = useFmt(); const { tt } = useI18n()
const rows = ref<any[]>([]); const units = ref<any[]>([]); const modal = ref(false); const editing = ref<any>(null); const err = ref('')
const form = reactive<any>({ name: '', name_en: '', price: 0, unit_id: '', is_active: true })
async function load() { rows.value = await api.get('/app/bookings/services'); units.value = await api.get('/app/bookings/units') }
onMounted(load)
function openNew() { editing.value = null; Object.assign(form, { name: '', name_en: '', price: 0, unit_id: '', is_active: true }); err.value = ''; modal.value = true }
function openEdit(s: any) { editing.value = s; Object.assign(form, { name: s.name, name_en: s.name_en || '', price: +s.price, unit_id: s.unit_id || '', is_active: s.is_active }); err.value = ''; modal.value = true }
async function save() { err.value = ''; try { const b = { ...form, unit_id: form.unit_id || null }; editing.value ? await api.patch(`/app/bookings/services/${editing.value.id}`, b) : await api.post('/app/bookings/services', b); modal.value = false; await load() } catch (e: any) { err.value = e.message } }
async function remove(s: any) { if (!await uiConfirm(tt(`حذف ${s.name}؟`,`Delete ${s.name}?`))) return; try { await api.del(`/app/bookings/services/${s.id}`); await load() } catch (e: any) { uiAlert(e.message) } }
</script>

<template>
  <div>
    <div class="breadcrumb"><NuxtLink to="/bookings">{{ tt('الحجوزات','Bookings') }}</NuxtLink> › <b>{{ tt('الخدمات الإضافية','Add-on Services') }}</b></div>
    <div class="card">
      <div class="row end" style="margin-bottom:12px"><button v-if="auth.can('bookings.manage')" class="btn" @click="openNew"><i class="ti ti-plus" /> {{ tt('إضافة خدمة','Add service') }}</button></div>
      <table class="table">
        <thead><tr><th>#</th><th>{{ tt('الاسم (عربي)','Name (Arabic)') }}</th><th>{{ tt('الاسم (إنجليزي)','Name (English)') }}</th><th>{{ tt('الوحدة','Unit') }}</th><th>{{ tt('السعر','Price') }}</th><th>{{ tt('الحالة','Status') }}</th><th></th></tr></thead>
        <tbody>
          <tr v-for="(s, i) in rows" :key="s.id">
            <td>{{ i + 1 }}</td><td><b>{{ s.name }}</b></td><td dir="ltr" style="text-align:right">{{ s.name_en || '—' }}</td><td>{{ s.unit?.name || tt('كل الوحدات','All units') }}</td><td class="num">{{ f.money(s.price) }}</td>
            <td><span class="badge" :class="s.is_active ? 'ok' : 'muted'">{{ s.is_active ? tt('مفعّل','Active') : tt('غير نشط','Inactive') }}</span></td>
            <td v-if="auth.can('bookings.manage')" class="row" style="gap:6px"><button class="btn ghost sm icon" @click="openEdit(s)"><i class="ti ti-pencil" /></button><button class="btn ghost sm icon" style="color:var(--primary)" @click="remove(s)"><i class="ti ti-trash" /></button></td>
          </tr>
        </tbody>
      </table>
      <p v-if="!rows.length" class="empty">{{ tt('لا توجد خدمات بعد (ضيافة، تصوير، كوشة، دي جي...).','No services yet (catering, photography, stage, DJ...).') }}</p>
    </div>
    <UiModal v-if="modal" :title="editing ? tt('تعديل خدمة','Edit service') : tt('خدمة جديدة','New service')" @close="modal = false">
      <div v-if="err" class="alert bad">{{ err }}</div>
      <div class="field"><label>{{ tt('الاسم (عربي)','Name (Arabic)') }}</label><input v-model="form.name" /></div>
      <div class="field"><label>{{ tt('الاسم (إنجليزي)','Name (English)') }}</label><input v-model="form.name_en" dir="ltr" /></div>
      <div class="field"><label>{{ tt('السعر','Price') }}</label><input v-model.number="form.price" type="number" step="0.001" dir="ltr" /></div>
      <div class="field"><label>{{ tt('الوحدة','Unit') }}</label><select v-model="form.unit_id"><option value="">{{ tt('كل الوحدات','All units') }}</option><option v-for="u in units" :key="u.id" :value="u.id">{{ u.name }}</option></select></div>
      <label class="switch"><input v-model="form.is_active" type="checkbox" /><span /> {{ tt('مفعّل','Active') }}</label>
      <template #footer><button class="btn ghost" @click="modal = false">{{ tt('إلغاء','Cancel') }}</button><button class="btn" @click="save">{{ tt('حفظ','Save') }}</button></template>
    </UiModal>
  </div>
</template>
