<script setup lang="ts">
definePageMeta({ title: 'الباقات' })
const api = useApi(); const auth = useAuth(); const f = useFmt()
const rows = ref<any[]>([]); const modal = ref(false); const editing = ref<any>(null); const err = ref('')
const form = reactive<any>({ name: '', type: 'umrah', price: 0, includes: '', is_active: true })
const typeLabel: any = { umrah: 'عمرة', hajj: 'حج', tourism: 'سياحة' }
async function load() { rows.value = await api.get('/app/travel/packages') }
onMounted(load)
function openNew() { editing.value = null; Object.assign(form, { name: '', type: 'umrah', price: 0, includes: '', is_active: true }); err.value = ''; modal.value = true }
function openEdit(p: any) { editing.value = p; Object.assign(form, { name: p.name, type: p.type, price: +p.price, includes: p.includes || '', is_active: p.is_active }); err.value = ''; modal.value = true }
async function save() { err.value = ''; try { editing.value ? await api.patch(`/app/travel/packages/${editing.value.id}`, form) : await api.post('/app/travel/packages', form); modal.value = false; await load() } catch (e: any) { err.value = e.message } }
async function remove(p: any) { if (!confirm(`حذف ${p.name}؟`)) return; try { await api.del(`/app/travel/packages/${p.id}`); await load() } catch (e: any) { alert(e.message) } }
</script>

<template>
  <div>
    <div class="breadcrumb"><NuxtLink to="/travel">الحج والعمرة والسفر</NuxtLink> › <b>الباقات</b></div>
    <div class="card">
      <div class="row end" style="margin-bottom:12px"><button v-if="auth.can('travel.manage')" class="btn" @click="openNew"><i class="ti ti-plus" /> باقة جديدة</button></div>
      <table class="table"><thead><tr><th>الباقة</th><th>النوع</th><th>السعر</th><th>تشمل</th><th>رحلات</th><th>الحالة</th><th></th></tr></thead>
        <tbody><tr v-for="p in rows" :key="p.id"><td><b>{{ p.name }}</b></td><td>{{ typeLabel[p.type] }}</td><td class="num">{{ f.money(p.price) }}</td><td class="muted" style="max-width:320px">{{ p.includes }}</td><td>{{ p.trips_count }}</td><td><span class="badge" :class="p.is_active ? 'ok' : 'muted'">{{ p.is_active ? 'نشطة' : 'موقوفة' }}</span></td>
          <td v-if="auth.can('travel.manage')" class="row" style="gap:6px"><button class="btn ghost sm icon" @click="openEdit(p)"><i class="ti ti-pencil" /></button><button class="btn ghost sm icon" style="color:var(--primary)" @click="remove(p)"><i class="ti ti-trash" /></button></td></tr></tbody></table>
      <p v-if="!rows.length" class="empty">لا توجد باقات (مثلاً: عمرة اقتصادية، عمرة VIP...).</p>
    </div>
    <UiModal v-if="modal" :title="editing ? 'تعديل باقة' : 'باقة جديدة'" @close="modal = false">
      <div v-if="err" class="alert bad">{{ err }}</div>
      <div class="field"><label>الاسم</label><input v-model="form.name" /></div>
      <div class="grid grid-2"><div class="field"><label>النوع</label><UiSelect v-model="form.type"><option v-for="(l, k) in typeLabel" :key="k" :value="k">{{ l }}</option></UiSelect></div><div class="field"><label>السعر للمسافر</label><input v-model.number="form.price" type="number" step="0.001" dir="ltr" /></div></div>
      <div class="field"><label>تشمل (فندق، طيران، نقل...)</label><textarea v-model="form.includes" rows="3" /></div>
      <label class="switch"><input v-model="form.is_active" type="checkbox" /><span /> نشطة</label>
      <template #footer><button class="btn ghost" @click="modal = false">إلغاء</button><button class="btn" @click="save">حفظ</button></template>
    </UiModal>
  </div>
</template>
