<script setup lang="ts">
definePageMeta({ title: 'الأدوار والصلاحيات', titleEn: 'Roles & Permissions' })
const api = useApi()
const { tt } = useI18n()
const roles = ref<any[]>([])
const groups = ref<any[]>([])
const modal = ref(false)
const editing = ref<any>(null)
const form = reactive<any>({ name_ar: '', permissions: [] as string[] })
const err = ref('')

async function load() { roles.value = await api.get('/app/roles'); groups.value = await api.get('/app/permissions') }
onMounted(load)

function openNew() { editing.value = null; Object.assign(form, { name_ar: '', permissions: [] }); err.value = ''; modal.value = true }
function openEdit(r: any) { editing.value = r; Object.assign(form, { name_ar: r.name_ar, permissions: [...r.permissions] }); err.value = ''; modal.value = true }
function toggle(k: string) { form.permissions.includes(k) ? form.permissions.splice(form.permissions.indexOf(k), 1) : form.permissions.push(k) }
function toggleGroup(g: any) {
  const keys = g.items.map((i: any) => i.key)
  const all = keys.every((k: string) => form.permissions.includes(k))
  form.permissions = all ? form.permissions.filter((k: string) => !keys.includes(k)) : [...new Set([...form.permissions, ...keys])]
}
async function save() {
  err.value = ''
  try { editing.value ? await api.patch(`/app/roles/${editing.value.id}`, form) : await api.post('/app/roles', form); modal.value = false; await load() }
  catch (e: any) { err.value = e.message }
}
async function remove(r: any) {
  if (!await uiConfirm(tt(`حذف الدور ${r.name_ar}؟`,`Delete role ${r.name_ar}?`))) return
  try { await api.del(`/app/roles/${r.id}`); await load() } catch (e: any) { uiAlert(e.message) }
}
const groupName = (g: string) => (g === 'core' ? tt('الإدارة العامة','General administration') : (groups.value.find(x => x.group === g)?.items[0]?.name_ar?.replace('عرض: ', '') || g))
</script>

<template>
  <div>
    <div class="breadcrumb">{{ tt('الإعدادات العامة','General settings') }} › <b>{{ tt('الأدوار والصلاحيات','Roles & permissions') }}</b></div>
    <div class="card">
      <div class="row between" style="margin-bottom:14px">
        <p class="muted" style="margin:0">{{ tt('الصلاحية «عرض» لكل وحدة تتحكم بظهورها في القائمة الجانبية.','The “view” permission of each module controls whether it appears in the sidebar.') }}</p>
        <button class="btn" @click="openNew"><i class="ti ti-plus" /> {{ tt('دور جديد','New role') }}</button>
      </div>
      <table class="table">
        <thead><tr><th>{{ tt('الدور','Role') }}</th><th>{{ tt('النوع','Type') }}</th><th>{{ tt('المستخدمون','Users') }}</th><th>{{ tt('الصلاحيات','Permissions') }}</th><th>{{ tt('الإجراءات','Actions') }}</th></tr></thead>
        <tbody>
          <tr v-for="r in roles" :key="r.id">
            <td><b>{{ tt(r.name_ar, r.name_en || r.name_ar) }}</b> <small class="muted" dir="ltr">({{ r.key }})</small></td>
            <td><span class="badge" :class="r.is_system ? 'info' : 'muted'">{{ r.is_system ? tt('نظامي','System') : tt('مخصص','Custom') }}</span></td>
            <td>{{ r.users_count }}</td>
            <td>{{ r.key === 'admin' ? tt('كل الصلاحيات','All permissions') : r.permissions.length }}</td>
            <td class="row" style="gap:6px">
              <button class="btn ghost sm icon" @click="openEdit(r)"><i class="ti ti-pencil" /></button>
              <button v-if="!r.is_system" class="btn ghost sm icon" style="color:var(--primary)" @click="remove(r)"><i class="ti ti-trash" /></button>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <UiModal v-if="modal" :title="editing ? tt('تعديل دور','Edit role') : tt('دور جديد','New role')" lg @close="modal = false">
      <div v-if="err" class="alert bad">{{ err }}</div>
      <div class="field"><label>{{ tt('اسم الدور','Role name') }}</label><input v-model="form.name_ar" /></div>
      <div v-if="editing?.key === 'admin'" class="alert info">{{ tt('دور «مدير النظام» يملك كل الصلاحيات دائماً.','The “System administrator” role always has all permissions.') }}</div>
      <template v-else>
        <div v-for="g in groups" :key="g.group" class="card" style="padding:12px;margin-top:10px;box-shadow:none;border:1px solid var(--line)">
          <div class="row between"><b>{{ groupName(g.group) }}</b><button class="btn ghost sm" @click="toggleGroup(g)">{{ tt('تحديد الكل','Select all') }}</button></div>
          <div class="chips mt"><span v-for="p in g.items" :key="p.key" class="chip" :class="{ on: form.permissions.includes(p.key) }" @click="toggle(p.key)">{{ tt(p.name_ar, p.name_en || p.name_ar) }}</span></div>
        </div>
      </template>
      <template #footer>
        <button class="btn ghost" @click="modal = false">{{ tt('إلغاء','Cancel') }}</button>
        <button class="btn" @click="save">{{ tt('حفظ','Save') }}</button>
      </template>
    </UiModal>
  </div>
</template>
