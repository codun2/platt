<script setup lang="ts">
definePageMeta({ title: 'موظف جديد', titleEn: 'New Employee' })
const api = useApi(); const auth = useAuth(); const { tt } = useI18n()
if (!auth.can('hr.manage')) navigateTo('/hr/employees')
const departments = ref<string[]>([]); const users = ref<any[]>([])
const err = ref(''); const saving = ref(false)
const form = reactive<any>(blankEmployee())
onMounted(async () => { const r = await api.get('/app/hr/employees', { all: 1 }); departments.value = r.stats?.departments || []; try { users.value = await api.get('/app/users') } catch { users.value = [] } })
async function save(stay = false) {
  err.value = ''; saving.value = true
  try { const e = await api.post('/app/hr/employees', employeePayload(form)); if (stay) Object.assign(form, blankEmployee()); else navigateTo(`/hr/employees/${e.id}`) }
  catch (x: any) { err.value = x.message; window.scrollTo({ top: 0, behavior: 'smooth' }) } finally { saving.value = false }
}
</script>

<template>
  <div>
    <div class="breadcrumb"><NuxtLink to="/hr/employees">{{ tt('الموظفون','Employees') }}</NuxtLink> › <b>{{ tt('موظف جديد','New employee') }}</b></div>
    <div class="card">
      <div v-if="err" class="alert bad">{{ err }}</div>
      <EmployeeForm :form="form" :departments="departments" :users="users" />
      <div class="row" style="gap:10px;margin-top:18px;border-top:1px solid var(--line);padding-top:16px">
        <button class="btn" :disabled="saving" @click="save(false)"><i class="ti ti-check" /> {{ tt('حفظ','Save') }}</button>
        <button class="btn ghost" :disabled="saving" @click="save(true)"><i class="ti ti-plus" /> {{ tt('حفظ وإضافة آخر','Save & add another') }}</button>
        <NuxtLink class="btn ghost" to="/hr/employees">{{ tt('إلغاء','Cancel') }}</NuxtLink>
      </div>
    </div>
  </div>
</template>
