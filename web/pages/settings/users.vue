<script setup lang="ts">
definePageMeta({ title: 'المستخدمون', titleEn: 'Users' })
const api = useApi()
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
  if (!confirm(`حذف المستخدم ${u.name}؟`)) return
  try { await api.del(`/app/users/${u.id}`); await load() } catch (e: any) { alert(e.message) }
}
</script>

<template>
  <div>
    <div class="breadcrumb">الإعدادات العامة › <b>المستخدمون</b></div>
    <div class="card">
      <div class="row between" style="margin-bottom:14px">
        <div class="row"><input v-model="q" placeholder="بحث بالاسم أو البريد..." style="padding:8px 12px;border:1px solid var(--line);border-radius:10px" @keyup.enter="load" /><button class="btn ghost" @click="load"><i class="ti ti-search" /></button></div>
        <button class="btn" @click="openNew"><i class="ti ti-plus" /> إضافة مستخدم</button>
      </div>
      <table class="table">
        <thead><tr><th>#</th><th>الاسم</th><th>البريد</th><th>الهاتف</th><th>الأدوار</th><th>الحالة</th><th>آخر دخول</th><th>الإجراءات</th></tr></thead>
        <tbody>
          <tr v-for="(u, i) in users" :key="u.id">
            <td>{{ i + 1 }}</td>
            <td><b>{{ u.name }}</b> <span v-if="u.is_owner" class="badge info">مالك</span></td>
            <td dir="ltr" style="text-align:right">{{ u.email }}</td>
            <td dir="ltr" style="text-align:right">{{ u.phone || '—' }}</td>
            <td><span v-for="r in u.roles" :key="r.id" class="badge muted" style="margin-left:4px">{{ r.name_ar }}</span></td>
            <td><span class="badge" :class="u.is_active ? 'ok' : 'bad'">{{ u.is_active ? 'مفعّل' : 'معطّل' }}</span></td>
            <td class="muted">{{ u.last_login_at ? u.last_login_at.slice(0, 16).replace('T', ' ') : '—' }}</td>
            <td class="row" style="gap:6px">
              <button class="btn ghost sm icon" @click="openEdit(u)"><i class="ti ti-pencil" /></button>
              <button v-if="!u.is_owner" class="btn ghost sm icon" style="color:var(--primary)" @click="remove(u)"><i class="ti ti-trash" /></button>
            </td>
          </tr>
        </tbody>
      </table>
      <p v-if="!users.length" class="empty">لا يوجد مستخدمون.</p>
    </div>

    <UiModal v-if="modal" :title="editing ? 'تعديل مستخدم' : 'مستخدم جديد'" @close="modal = false">
      <div v-if="err" class="alert bad">{{ err }}</div>
      <div class="field"><label>الاسم</label><input v-model="form.name" /></div>
      <div class="field"><label>البريد الإلكتروني</label><input v-model="form.email" type="email" dir="ltr" /></div>
      <div class="field"><label>الهاتف</label><input v-model="form.phone" dir="ltr" /></div>
      <div class="field"><label>كلمة المرور {{ editing ? '(اتركها فارغة للإبقاء)' : '' }}</label><input v-model="form.password" type="password" dir="ltr" /></div>
      <div class="field"><label>الأدوار</label>
        <div class="chips"><span v-for="r in roles" :key="r.id" class="chip" :class="{ on: form.roles.includes(r.id) }" @click="toggleRole(r.id)">{{ r.name_ar }}</span></div>
      </div>
      <label v-if="!editing?.is_owner" class="switch"><input v-model="form.is_active" type="checkbox" /><span /> مفعّل</label>
      <template #footer>
        <button class="btn ghost" @click="modal = false">إلغاء</button>
        <button class="btn" @click="save">حفظ</button>
      </template>
    </UiModal>
  </div>
</template>
