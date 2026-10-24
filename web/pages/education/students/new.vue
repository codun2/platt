<script setup lang="ts">
definePageMeta({ title: 'تسجيل طالب', titleEn: 'Enroll Student' })
const api = useApi(); const auth = useAuth(); const route = useRoute()
if (!auth.can('education.manage')) navigateTo('/education/students')
const err = ref(''); const saving = ref(false); const classes = ref<any[]>([]); const guardians = ref<any[]>([])
const form = reactive<any>(blankStudent())
if (route.query.class_id) form.class_id = Number(route.query.class_id)
useDraft('student-new', form)
onMounted(async () => { [classes.value, guardians.value] = await Promise.all([api.get('/app/education/classes', { active_only: 1 }), api.get('/app/customers', { all: 1, active_only: 1 })]) })
async function save(another = false) {
  err.value = ''; saving.value = true
  try {
    const s = await api.post('/app/education/students', studentPayload(form))
    if (another) { const keep = { class_id: form.class_id, guardian_id: s.guardian_id, guardian_relation: form.guardian_relation }; Object.assign(form, blankStudent(), keep); guardians.value = await api.get('/app/customers', { all: 1, active_only: 1 }) }
    else navigateTo(`/education/students/${s.id}`)
  } catch (e: any) { err.value = e.message; window.scrollTo({ top: 0, behavior: 'smooth' }) } finally { saving.value = false }
}
</script>

<template>
  <div>
    <div class="breadcrumb"><NuxtLink to="/education/students">{{ tt('الطلاب', 'Students') }}</NuxtLink> › <b>{{ tt('تسجيل طالب', 'Enroll') }}</b></div>
    <div class="card">
      <div v-if="err" class="alert bad">{{ err }}</div>
      <StudentForm :form="form" :classes="classes" :guardians="guardians" />
      <div class="row" style="gap:10px;margin-top:18px;border-top:1px solid var(--line);padding-top:16px">
        <button class="btn" :disabled="saving" @click="save(false)"><i class="ti ti-check" /> {{ tt('حفظ', 'Save') }}</button>
        <button class="btn ghost" :disabled="saving" @click="save(true)"><i class="ti ti-users-plus" /> {{ tt('حفظ وتسجيل أخ / أخت', 'Save & add sibling') }}</button>
        <NuxtLink class="btn ghost" to="/education/students">{{ tt('إلغاء', 'Cancel') }}</NuxtLink>
      </div>
    </div>
  </div>
</template>
