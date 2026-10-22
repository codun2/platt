<script setup lang="ts">
definePageMeta({ layout: 'super', title: 'خطط الأسعار', titleEn: 'Pricing Plans' })
const api = useApi()
const { tt } = useI18n()
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
async function remove(p: any) { if (!confirm(tt(`حذف الخطة ${p.name_ar}؟`,`Delete plan ${p.name_ar}?`))) return; try { await api.del(`/super/plans/${p.id}`); await load() } catch (e: any) { alert(e.message) } }
</script>

<template>
  <div class="card">
    <div class="row between" style="margin-bottom:14px"><p class="muted" style="margin:0">{{ tt('الخطط الشهرية التي يشترك بها العملاء.','Monthly plans tenants subscribe to.') }}</p><button class="btn" @click="openNew"><i class="ti ti-plus" /> {{ tt('خطة جديدة','New plan') }}</button></div>
    <table class="table">
      <thead><tr><th>{{ tt('الخطة','Plan') }}</th><th>{{ tt('السعر الشهري','Monthly price') }}</th><th>{{ tt('حد المستخدمين','User limit') }}</th><th>{{ tt('المشتركون','Tenants') }}</th><th>{{ tt('الحالة','Status') }}</th><th></th></tr></thead>
      <tbody>
        <tr v-for="p in plans" :key="p.id">
          <td><b>{{ tt(p.name_ar, p.name_en || p.name_ar) }}</b> <small class="muted" dir="ltr">({{ p.key }})</small><br /><small class="muted">{{ p.description_ar }}</small></td>
          <td class="num">{{ p.price_monthly }} {{ tt('ريال','SAR') }}</td><td>{{ p.max_users }}</td><td>{{ p.tenants_count }}</td>
          <td><span class="badge" :class="p.is_active ? 'ok' : 'muted'">{{ p.is_active ? tt('نشطة','Active') : tt('معطّلة','Disabled') }}</span></td>
          <td class="row" style="gap:6px"><button class="btn ghost sm icon" @click="openEdit(p)"><i class="ti ti-pencil" /></button><button class="btn ghost sm icon" style="color:var(--primary)" @click="remove(p)"><i class="ti ti-trash" /></button></td>
        </tr>
      </tbody>
    </table>

    <UiModal v-if="modal" :title="editing ? tt('تعديل خطة','Edit plan') : tt('خطة جديدة','New plan')" @close="modal = false">
      <div v-if="err" class="alert bad">{{ err }}</div>
      <div class="grid grid-2">
        <div class="field"><label>{{ tt('المفتاح','Key') }}</label><input v-model="form.key" dir="ltr" /></div>
        <div class="field"><label>{{ tt('الاسم (عربي)','Name (Arabic)') }}</label><input v-model="form.name_ar" /></div>
        <div class="field"><label>{{ tt('الاسم (إنجليزي)','Name (English)') }}</label><input v-model="form.name_en" dir="ltr" /></div>
        <div class="field"><label>{{ tt('السعر الشهري (ريال)','Monthly price (SAR)') }}</label><input v-model.number="form.price_monthly" type="number" step="0.001" dir="ltr" /></div>
        <div class="field"><label>{{ tt('حد المستخدمين','User limit') }}</label><input v-model.number="form.max_users" type="number" min="1" /></div>
        <div class="field"><label>{{ tt('الترتيب','Order') }}</label><input v-model.number="form.sort" type="number" /></div>
      </div>
      <div class="field"><label>{{ tt('الوصف','Description') }}</label><input v-model="form.description_ar" /></div>
      <label class="switch"><input v-model="form.is_active" type="checkbox" /><span /> {{ tt('نشطة','Active') }}</label>
      <template #footer><button class="btn ghost" @click="modal = false">{{ tt('إلغاء','Cancel') }}</button><button class="btn" @click="save">{{ tt('حفظ','Save') }}</button></template>
    </UiModal>
  </div>
</template>
