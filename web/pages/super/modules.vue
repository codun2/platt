<script setup lang="ts">
definePageMeta({ layout: 'super', title: 'سجل الوحدات', titleEn: 'Modules Registry' })
const api = useApi()
const { tt } = useI18n()
const modules = ref<any[]>([])
const modal = ref(false); const editing = ref<any>(null); const err = ref('')
const form = reactive<any>({ key: '', name_ar: '', name_en: '', icon: '', route: '', parent_key: '', engine: 'core', sort: 0, is_core: false, is_active: true })
const parents = computed(() => modules.value.filter(m => !m.parent_key))

async function load() { modules.value = await api.get('/super/modules') }
onMounted(load)
function openNew() { editing.value = null; Object.assign(form, { key: '', name_ar: '', name_en: '', icon: 'ti-apps', route: '', parent_key: '', engine: 'core', sort: (modules.value.length + 1) * 10, is_core: false, is_active: true }); err.value = ''; modal.value = true }
function openEdit(m: any) { editing.value = m; Object.assign(form, { ...m, parent_key: m.parent_key || '', name_en: m.name_en || '', icon: m.icon || '', route: m.route || '' }); err.value = ''; modal.value = true }
async function save() {
  err.value = ''
  const body = { ...form, parent_key: form.parent_key || null }
  try { editing.value ? await api.patch(`/super/modules/${editing.value.id}`, body) : await api.post('/super/modules', body); modal.value = false; await load() }
  catch (e: any) { err.value = e.message }
}
async function remove(m: any) { if (!confirm(tt(`حذف الوحدة ${m.name_ar}؟`,`Delete module ${m.name_ar}?`))) return; try { await api.del(`/super/modules/${m.id}`); await load() } catch (e: any) { alert(e.message) } }
const tree = computed(() => parents.value.map(p => ({ ...p, children: modules.value.filter(c => c.parent_key === p.key) })))
</script>

<template>
  <div class="card">
    <div class="row between" style="margin-bottom:14px">
      <p class="muted" style="margin:0">{{ tt('القائمة الجانبية لكل مشترك تُولَّد من هذا الجدول.','Each tenant\'s sidebar is generated from this table.') }} <a href="https://tabler.io/icons" target="_blank" style="color:var(--info)">Tabler</a> {{ tt('(مثل','(e.g.') }} <code>ti-users</code>).</p>
      <button class="btn" @click="openNew"><i class="ti ti-plus" /> {{ tt('وحدة جديدة','New module') }}</button>
    </div>
    <table class="table">
      <thead><tr><th>{{ tt('المفتاح','Key') }}</th><th>{{ tt('الاسم','Name') }}</th><th>{{ tt('المسار','Route') }}</th><th>{{ tt('المحرّك','Engine') }}</th><th>{{ tt('الترتيب','Order') }}</th><th>{{ tt('الحالة','Status') }}</th><th></th></tr></thead>
      <tbody>
        <template v-for="p in tree" :key="p.id">
          <tr style="background:#f8fafc">
            <td dir="ltr" style="text-align:right"><code>{{ p.key }}</code></td>
            <td><b><i :class="`ti ${p.icon}`" /> {{ tt(p.name_ar, p.name_en || p.name_ar) }}</b> <span v-if="p.is_core" class="badge info">{{ tt('أساسية','Core') }}</span></td>
            <td dir="ltr" style="text-align:right" class="muted">{{ p.route }}</td><td>{{ p.engine }}</td><td>{{ p.sort }}</td>
            <td><span class="badge" :class="p.is_active ? 'ok' : 'muted'">{{ p.is_active ? tt('نشطة','Active') : tt('معطّلة','Disabled') }}</span></td>
            <td class="row" style="gap:6px"><button class="btn ghost sm icon" @click="openEdit(p)"><i class="ti ti-pencil" /></button><button v-if="!p.is_core" class="btn ghost sm icon" style="color:var(--primary)" @click="remove(p)"><i class="ti ti-trash" /></button></td>
          </tr>
          <tr v-for="c in p.children" :key="c.id">
            <td dir="ltr" style="text-align:right;padding-right:32px"><code>{{ c.key }}</code></td>
            <td style="padding-right:32px"><i :class="`ti ${c.icon}`" /> {{ tt(c.name_ar, c.name_en || c.name_ar) }}</td>
            <td dir="ltr" style="text-align:right" class="muted">{{ c.route }}</td><td>{{ c.engine }}</td><td>{{ c.sort }}</td>
            <td><span class="badge" :class="c.is_active ? 'ok' : 'muted'">{{ c.is_active ? tt('نشطة','Active') : tt('معطّلة','Disabled') }}</span></td>
            <td class="row" style="gap:6px"><button class="btn ghost sm icon" @click="openEdit(c)"><i class="ti ti-pencil" /></button><button v-if="!c.is_core" class="btn ghost sm icon" style="color:var(--primary)" @click="remove(c)"><i class="ti ti-trash" /></button></td>
          </tr>
        </template>
      </tbody>
    </table>

    <UiModal v-if="modal" :title="editing ? tt('تعديل وحدة','Edit module') : tt('وحدة جديدة','New module')" @close="modal = false">
      <div v-if="err" class="alert bad">{{ err }}</div>
      <div class="grid grid-2">
        <div class="field"><label>{{ tt('المفتاح (a-z, 0-9, _)','Key (a-z, 0-9, _)') }}</label><input v-model="form.key" dir="ltr" /></div>
        <div class="field"><label>{{ tt('الوحدة الأم','Parent module') }}</label><select v-model="form.parent_key"><option value="">{{ tt('— رئيسية —','— top level —') }}</option><option v-for="p in parents" :key="p.key" :value="p.key">{{ tt(p.name_ar, p.name_en || p.name_ar) }}</option></select></div>
        <div class="field"><label>{{ tt('الاسم (عربي)','Name (Arabic)') }}</label><input v-model="form.name_ar" /></div>
        <div class="field"><label>{{ tt('الاسم (إنجليزي)','Name (English)') }}</label><input v-model="form.name_en" dir="ltr" /></div>
        <div class="field"><label>{{ tt('الأيقونة','Icon') }}</label><input v-model="form.icon" dir="ltr" /></div>
        <div class="field"><label>{{ tt('المسار','Route') }}</label><input v-model="form.route" dir="ltr" /></div>
        <div class="field"><label>{{ tt('المحرّك','Engine') }}</label><select v-model="form.engine"><option v-for="e in ['core','inventory','bookings','rental','service','projects','education','manufacturing']" :key="e" :value="e">{{ e }}</option></select></div>
        <div class="field"><label>{{ tt('الترتيب','Order') }}</label><input v-model.number="form.sort" type="number" /></div>
      </div>
      <div class="row"><label class="switch"><input v-model="form.is_core" type="checkbox" /><span /> {{ tt('أساسية (لكل المشتركين)','Core (for all tenants)') }}</label><label class="switch"><input v-model="form.is_active" type="checkbox" /><span /> {{ tt('نشطة','Active') }}</label></div>
      <template #footer><button class="btn ghost" @click="modal = false">{{ tt('إلغاء','Cancel') }}</button><button class="btn" @click="save">{{ tt('حفظ','Save') }}</button></template>
    </UiModal>
  </div>
</template>
