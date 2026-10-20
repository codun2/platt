<script setup lang="ts">
definePageMeta({ layout: 'super', title: 'الباقات حسب النشاط' })
const api = useApi()
const presets = ref<any[]>([]); const modules = ref<any[]>([])
const modal = ref(false); const editing = ref<any>(null); const err = ref('')
const form = reactive<any>({ key: '', name_ar: '', name_en: '', icon: '', engine: '', description_ar: '', sort: 0, is_active: true, module_ids: [] as number[] })
const tops = computed(() => modules.value.filter(m => !m.parent_key && !m.is_core))

async function load() { presets.value = await api.get('/super/presets'); modules.value = await api.get('/super/modules') }
onMounted(load)
function openNew() { editing.value = null; Object.assign(form, { key: '', name_ar: '', name_en: '', icon: 'ti-building', engine: 'core', description_ar: '', sort: (presets.value.length + 1) * 10, is_active: true, module_ids: [] }); err.value = ''; modal.value = true }
function openEdit(p: any) { editing.value = p; Object.assign(form, { ...p, name_en: p.name_en || '', icon: p.icon || '', engine: p.engine || '', description_ar: p.description_ar || '', module_ids: [...p.module_ids] }); err.value = ''; modal.value = true }
// Toggle a top-level module together with all its children.
function toggleTop(m: any) {
  const ids = [m.id, ...modules.value.filter(c => c.parent_key === m.key).map(c => c.id)]
  const on = form.module_ids.includes(m.id)
  form.module_ids = on ? form.module_ids.filter((i: number) => !ids.includes(i)) : [...new Set([...form.module_ids, ...ids])]
}
async function save() {
  err.value = ''
  try { editing.value ? await api.patch(`/super/presets/${editing.value.id}`, form) : await api.post('/super/presets', form); modal.value = false; await load() }
  catch (e: any) { err.value = e.message }
}
async function remove(p: any) { if (!confirm(`حذف الباقة ${p.name_ar}؟`)) return; try { await api.del(`/super/presets/${p.id}`); await load() } catch (e: any) { alert(e.message) } }
const topNames = (p: any) => p.modules.filter((m: any) => tops.value.some(t => t.id === m.id)).map((m: any) => m.name_ar)
</script>

<template>
  <div class="card">
    <div class="row between" style="margin-bottom:14px">
      <p class="muted" style="margin:0">عند التسجيل يختار المشترك قطاعه فتُنسخ وحدات الباقة إلى حسابه.</p>
      <button class="btn" @click="openNew"><i class="ti ti-plus" /> باقة جديدة</button>
    </div>
    <table class="table">
      <thead><tr><th>الباقة</th><th>المحرّك</th><th>الوحدات</th><th>المشتركون</th><th>الحالة</th><th></th></tr></thead>
      <tbody>
        <tr v-for="p in presets" :key="p.id">
          <td><b><i :class="`ti ${p.icon}`" /> {{ p.name_ar }}</b><br /><small class="muted">{{ p.description_ar }}</small></td>
          <td>{{ p.engine }}</td>
          <td><span v-for="n in topNames(p)" :key="n" class="badge muted" style="margin-left:4px">{{ n }}</span></td>
          <td>{{ p.tenants_count }}</td>
          <td><span class="badge" :class="p.is_active ? 'ok' : 'muted'">{{ p.is_active ? 'نشطة' : 'معطّلة' }}</span></td>
          <td class="row" style="gap:6px"><button class="btn ghost sm icon" @click="openEdit(p)"><i class="ti ti-pencil" /></button><button class="btn ghost sm icon" style="color:var(--primary)" @click="remove(p)"><i class="ti ti-trash" /></button></td>
        </tr>
      </tbody>
    </table>

    <UiModal v-if="modal" :title="editing ? 'تعديل باقة' : 'باقة جديدة'" lg @close="modal = false">
      <div v-if="err" class="alert bad">{{ err }}</div>
      <div class="grid grid-2">
        <div class="field"><label>المفتاح</label><input v-model="form.key" dir="ltr" /></div>
        <div class="field"><label>الاسم (عربي)</label><input v-model="form.name_ar" /></div>
        <div class="field"><label>الاسم (إنجليزي)</label><input v-model="form.name_en" dir="ltr" /></div>
        <div class="field"><label>الأيقونة</label><input v-model="form.icon" dir="ltr" /></div>
        <div class="field"><label>المحرّك الرئيسي</label><input v-model="form.engine" dir="ltr" /></div>
        <div class="field"><label>الترتيب</label><input v-model.number="form.sort" type="number" /></div>
      </div>
      <div class="field"><label>الوصف</label><input v-model="form.description_ar" /></div>
      <div class="field"><label>الوحدات المفعّلة (تشمل الوحدات الفرعية)</label>
        <div class="chips"><span v-for="m in tops" :key="m.id" class="chip" :class="{ on: form.module_ids.includes(m.id) }" @click="toggleTop(m)"><i :class="`ti ${m.icon}`" /> {{ m.name_ar }}</span></div>
      </div>
      <label class="switch"><input v-model="form.is_active" type="checkbox" /><span /> نشطة</label>
      <template #footer><button class="btn ghost" @click="modal = false">إلغاء</button><button class="btn" @click="save">حفظ</button></template>
    </UiModal>
  </div>
</template>
