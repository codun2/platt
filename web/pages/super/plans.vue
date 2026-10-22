<script setup lang="ts">
definePageMeta({ layout: 'super', title: 'خطط الأسعار' })
const api = useApi()
const plans = ref<any[]>([])
const modal = ref(false); const editing = ref<any>(null); const err = ref('')
const form = reactive<any>({ key: '', name_ar: '', name_en: '', price_monthly: 0, max_users: 5, description_ar: '', sort: 0, is_active: true })

async function load() { plans.value = await api.get('/super/plans') }
onMounted(load)
function openNew() { editing.value = null; Object.assign(form, { key: '', name_ar: '', name_en: '', price_monthly: 0, max_users: 5, description_ar: '', sort: (plans.value.length + 1) * 10, is_active: true }); err.value = ''; modal.value = true }
function openEdit(p: any) { editing.value = p; Object.assign(form, { ...p, name_en: p.name_en || '', description_ar: p.description_ar || '' }); err.value = ''; modal.value = true }
async function save() {
  err.value = ''
  try { editing.value ? await api.patch(`/super/plans/${editing.value.id}`, form) : await api.post('/super/plans', form); modal.value = false; await load() }
  catch (e: any) { err.value = e.message }
}
async function remove(p: any) { if (!confirm(`حذف الخطة ${p.name_ar}؟`)) return; try { await api.del(`/super/plans/${p.id}`); await load() } catch (e: any) { alert(e.message) } }
</script>

<template>
  <div class="card">
    <div class="row between" style="margin-bottom:14px"><p class="muted" style="margin:0">الخطط الشهرية التي يشترك بها العملاء.</p><button class="btn" @click="openNew"><i class="ti ti-plus" /> خطة جديدة</button></div>
    <table class="table">
      <thead><tr><th>الخطة</th><th>السعر الشهري</th><th>حد المستخدمين</th><th>المشتركون</th><th>الحالة</th><th></th></tr></thead>
      <tbody>
        <tr v-for="p in plans" :key="p.id">
          <td><b>{{ p.name_ar }}</b> <small class="muted" dir="ltr">({{ p.key }})</small><br /><small class="muted">{{ p.description_ar }}</small></td>
          <td class="num">{{ p.price_monthly }} ريال</td><td>{{ p.max_users }}</td><td>{{ p.tenants_count }}</td>
          <td><span class="badge" :class="p.is_active ? 'ok' : 'muted'">{{ p.is_active ? 'نشطة' : 'معطّلة' }}</span></td>
          <td class="row" style="gap:6px"><button class="btn ghost sm icon" @click="openEdit(p)"><i class="ti ti-pencil" /></button><button class="btn ghost sm icon" style="color:var(--primary)" @click="remove(p)"><i class="ti ti-trash" /></button></td>
        </tr>
      </tbody>
    </table>

    <UiModal v-if="modal" :title="editing ? 'تعديل خطة' : 'خطة جديدة'" @close="modal = false">
      <div v-if="err" class="alert bad">{{ err }}</div>
      <div class="grid grid-2">
        <div class="field"><label>المفتاح</label><input v-model="form.key" dir="ltr" /></div>
        <div class="field"><label>الاسم (عربي)</label><input v-model="form.name_ar" /></div>
        <div class="field"><label>الاسم (إنجليزي)</label><input v-model="form.name_en" dir="ltr" /></div>
        <div class="field"><label>السعر الشهري (ريال)</label><input v-model.number="form.price_monthly" type="number" step="0.001" dir="ltr" /></div>
        <div class="field"><label>حد المستخدمين</label><input v-model.number="form.max_users" type="number" min="1" /></div>
        <div class="field"><label>الترتيب</label><input v-model.number="form.sort" type="number" /></div>
      </div>
      <div class="field"><label>الوصف</label><input v-model="form.description_ar" /></div>
      <label class="switch"><input v-model="form.is_active" type="checkbox" /><span /> نشطة</label>
      <template #footer><button class="btn ghost" @click="modal = false">إلغاء</button><button class="btn" @click="save">حفظ</button></template>
    </UiModal>
  </div>
</template>
