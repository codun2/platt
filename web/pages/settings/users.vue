<script setup lang="ts">
definePageMeta({ title: 'المستخدمون', titleEn: 'Users' })
const api = useApi()
const { tt } = useI18n()
const users = ref<any[]>([])
const roles = ref<any[]>([])
const q = ref('')
const modal = ref(false)
const editing = ref<any>(null)
const form = reactive<any>({ name: '', email: '', phone: '', password: '', is_active: true, roles: [] })
const err = ref('')

async function load() {
  users.value = await api.get('/app/users', { q: q.value })
  roles.value = await api.get('/app/roles')
}
onMounted(load)

function openNew() { editing.value = null; Object.assign(form, { name: '', email: '', phone: '', password: '', is_active: true, roles: [] }); err.value = ''; modal.value = true }
function openEdit(u: any) { editing.value = u; Object.assign(form, { name: u.name, email: u.email, phone: u.phone || '', password: '', is_active: u.is_active, roles: u.roles.map((r: any) => r.id) }); err.value = ''; modal.value = true }
function toggleRole(id: number) { form.roles.includes(id) ? form.roles.splice(form.roles.indexOf(id), 1) : form.roles.push(id) }

async function save() {
  err.value = ''
  try {
    editing.value ? await api.patch(`/app/users/${editing.value.id}`, form) : await api.post('/app/users', form)
    modal.value = false; await load()
  } catch (e: any) { err.value = e.message }
}
async function remove(u: any) {
  if (!await uiConfirm(tt(`حذف المستخدم ${u.name}؟`,`Delete user ${u.name}?`))) return
  try { await api.del(`/app/users/${u.id}`); await load() } catch (e: any) { uiAlert(e.message) }
}
</script>

<template>
  <div>
    <div class="breadcrumb">{{ tt('الإعدادات العامة','General settings') }} › <b>{{ tt('المستخدمون','Users') }}</b></div>
    <div class="card">
      <div class="row between" style="margin-bottom:14px">
        <div class="row"><input v-model="q" :placeholder="tt('بحث بالاسم أو البريد...','Search by name or email...')" style="padding:8px 12px;border:1px solid var(--line);border-radius:10px" @keyup.enter="load" /><button class="btn ghost" @click="load"><i class="ti ti-search" /></button></div>
        <button class="btn" @click="openNew"><i class="ti ti-plus" /> {{ tt('إضافة مستخدم','Add user') }}</button>
      </div>
      <table class="table">
        <thead><tr><th>#</th><th>{{ tt('الاسم','Name') }}</th><th>{{ tt('البريد','Email') }}</th><th>{{ tt('الهاتف','Phone') }}</th><th>{{ tt('الأدوار','Roles') }}</th><th>{{ tt('الحالة','Status') }}</th><th>{{ tt('آخر دخول','Last login') }}</th><th>{{ tt('الإجراءات','Actions') }}</th></tr></thead>
        <tbody>
          <tr v-for="(u, i) in users" :key="u.id">
            <td>{{ i + 1 }}</td>
            <td><b>{{ u.name }}</b> <span v-if="u.is_owner" class="badge info">{{ tt('مالك','Owner') }}</span></td>
            <td dir="ltr" style="text-align:right">{{ u.email }}</td>
            <td dir="ltr" style="text-align:right">{{ u.phone || '—' }}</td>
            <td><span v-for="r in u.roles" :key="r.id" class="badge muted" style="margin-left:4px">{{ tt(r.name_ar, r.name_en || r.name_ar) }}</span></td>
            <td><span class="badge" :class="u.is_active ? 'ok' : 'bad'">{{ u.is_active ? tt('مفعّل','Active') : tt('معطّل','Disabled') }}</span></td>
            <td class="muted">{{ u.last_login_at ? u.last_login_at.slice(0, 16).replace('T', ' ') : '—' }}</td>
            <td class="row" style="gap:6px">
              <button class="btn ghost sm icon" @click="openEdit(u)"><i class="ti ti-pencil" /></button>
              <button v-if="!u.is_owner" class="btn ghost sm icon" style="color:var(--primary)" @click="remove(u)"><i class="ti ti-trash" /></button>
            </td>
          </tr>
        </tbody>
      </table>
      <p v-if="!users.length" class="empty">{{ tt('لا يوجد مستخدمون.','No users.') }}</p>
    </div>

    <UiModal v-if="modal" :title="editing ? tt('تعديل مستخدم','Edit user') : tt('مستخدم جديد','New user')" @close="modal = false">
      <div v-if="err" class="alert bad">{{ err }}</div>
      <div class="field"><label>{{ tt('الاسم','Name') }}</label><input v-model="form.name" /></div>
      <div class="field"><label>{{ tt('البريد الإلكتروني','Email') }}</label><input v-model="form.email" type="email" dir="ltr" /></div>
      <div class="field"><label>{{ tt('الهاتف','Phone') }}</label><input v-model="form.phone" dir="ltr" /></div>
      <div class="field"><label>{{ tt('كلمة المرور','Password') }} {{ editing ? tt('(اتركها فارغة للإبقاء)','(leave blank to keep)') : '' }}</label><input v-model="form.password" type="password" dir="ltr" /></div>
      <div class="field"><label>{{ tt('الأدوار','Roles') }}</label>
        <div class="chips"><span v-for="r in roles" :key="r.id" class="chip" :class="{ on: form.roles.includes(r.id) }" @click="toggleRole(r.id)">{{ tt(r.name_ar, r.name_en || r.name_ar) }}</span></div>
      </div>
      <label v-if="!editing?.is_owner" class="switch"><input v-model="form.is_active" type="checkbox" /><span /> {{ tt('مفعّل','Active') }}</label>
      <template #footer>
        <button class="btn ghost" @click="modal = false">{{ tt('إلغاء','Cancel') }}</button>
        <button class="btn" @click="save">{{ tt('حفظ','Save') }}</button>
      </template>
    </UiModal>
  </div>
</template>
