<script setup lang="ts">
definePageMeta({ title: 'الباقات', titleEn: 'Packages' })
const api = useApi(); const auth = useAuth(); const f = useFmt(); const { tt } = useI18n()
const rows = ref<any[]>([]); const modal = ref(false); const editing = ref<any>(null); const err = ref('')
const form = reactive<any>({ name: '', type: 'umrah', price: 0, includes: '', is_active: true })
const typeLabel: any = { umrah: L('عمرة','Umrah'), hajj: L('حج','Hajj'), tourism: L('سياحة','Tourism') }
async function load() { rows.value = await api.get('/app/travel/packages') }
onMounted(load)
function openNew() { editing.value = null; Object.assign(form, { name: '', type: 'umrah', price: 0, includes: '', is_active: true }); err.value = ''; modal.value = true }
function openEdit(p: any) { editing.value = p; Object.assign(form, { name: p.name, type: p.type, price: +p.price, includes: p.includes || '', is_active: p.is_active }); err.value = ''; modal.value = true }
async function save() { err.value = ''; try { editing.value ? await api.patch(`/app/travel/packages/${editing.value.id}`, form) : await api.post('/app/travel/packages', form); modal.value = false; await load() } catch (e: any) { err.value = e.message } }
async function remove(p: any) { if (!confirm(tt(`حذف ${p.name}؟`,`Delete ${p.name}?`))) return; try { await api.del(`/app/travel/packages/${p.id}`); await load() } catch (e: any) { alert(e.message) } }
</script>

<template>
  <div>
    <div class="breadcrumb"><NuxtLink to="/travel">{{ tt('الحج والعمرة والسفر','Hajj, Umrah & Travel') }}</NuxtLink> › <b>{{ tt('الباقات','Packages') }}</b></div>
    <div class="card">
      <div class="row end" style="margin-bottom:12px"><button v-if="auth.can('travel.manage')" class="btn" @click="openNew"><i class="ti ti-plus" /> {{ tt('باقة جديدة','New package') }}</button></div>
      <table class="table"><thead><tr><th>{{ tt('الباقة','Package') }}</th><th>{{ tt('النوع','Type') }}</th><th>{{ tt('السعر','Price') }}</th><th>{{ tt('تشمل','Includes') }}</th><th>{{ tt('رحلات','Trips') }}</th><th>{{ tt('الحالة','Status') }}</th><th></th></tr></thead>
        <tbody><tr v-for="p in rows" :key="p.id"><td><b>{{ p.name }}</b></td><td>{{ typeLabel[p.type] }}</td><td class="num">{{ f.money(p.price) }}</td><td class="muted" style="max-width:320px">{{ p.includes }}</td><td>{{ p.trips_count }}</td><td><span class="badge" :class="p.is_active ? 'ok' : 'muted'">{{ p.is_active ? 'نشطة' : 'موقوفة' }}</span></td>
          <td v-if="auth.can('travel.manage')" class="row" style="gap:6px"><button class="btn ghost sm icon" @click="openEdit(p)"><i class="ti ti-pencil" /></button><button class="btn ghost sm icon" style="color:var(--primary)" @click="remove(p)"><i class="ti ti-trash" /></button></td></tr></tbody></table>
      <p v-if="!rows.length" class="empty">{{ tt('لا توجد باقات (مثلاً: عمرة اقتصادية، عمرة VIP...).','No packages (e.g. Economy Umrah, VIP Umrah...).') }}</p>
    </div>
    <UiModal v-if="modal" :title="editing ? tt('تعديل باقة','Edit package') : tt('باقة جديدة','New package')" @close="modal = false">
      <div v-if="err" class="alert bad">{{ err }}</div>
      <div class="field"><label>{{ tt('الاسم','Name') }}</label><input v-model="form.name" /></div>
      <div class="grid grid-2"><div class="field"><label>{{ tt('النوع','Type') }}</label><UiSelect v-model="form.type"><option v-for="(l, k) in typeLabel" :key="k" :value="k">{{ l }}</option></UiSelect></div><div class="field"><label>{{ tt('السعر للمسافر','Price per traveler') }}</label><input v-model.number="form.price" type="number" step="0.001" dir="ltr" /></div></div>
      <div class="field"><label>{{ tt('تشمل (فندق، طيران، نقل...)','Includes (hotel, flight, transport...)') }}</label><textarea v-model="form.includes" rows="3" /></div>
      <label class="switch"><input v-model="form.is_active" type="checkbox" /><span /> {{ tt('نشطة','Active') }}</label>
      <template #footer><button class="btn ghost" @click="modal = false">{{ tt('إلغاء','Cancel') }}</button><button class="btn" @click="save">{{ tt('حفظ','Save') }}</button></template>
    </UiModal>
  </div>
</template>
