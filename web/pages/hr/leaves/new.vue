<script setup lang="ts">
definePageMeta({ title: 'طلب إجازة' })
const api = useApi(); const auth = useAuth(); const route = useRoute()
if (!auth.can('hr.manage')) navigateTo('/hr/attendance?tab=leaves')
const employees = ref<any[]>([]); const err = ref(''); const saving = ref(false)
const form = reactive<any>(blankLeave(route.query.employee_id ? String(route.query.employee_id) : ''))
const days = computed(() => leaveDays(form.from, form.to))
const employee = computed(() => employees.value.find((e: any) => String(e.id) === String(form.employee_id)))
onMounted(async () => { employees.value = (await api.get('/app/hr/employees', { all: 1, status: 'active' })).data })
async function save() {
  err.value = ''; saving.value = true
  try { const l = await api.post('/app/hr/leaves', form); navigateTo(`/hr/leaves/${l.id}`) }
  catch (x: any) { err.value = x.message } finally { saving.value = false }
}
</script>

<template>
  <div>
    <div class="breadcrumb"><NuxtLink to="/hr/attendance?tab=leaves">الإجازات</NuxtLink> › <b>طلب إجازة</b></div>
    <div class="card">
      <div v-if="err" class="alert bad">{{ err }}</div>
      <div class="card-title"><i class="ti ti-beach" /> بيانات الإجازة</div>
      <div class="grid grid-3">
        <div class="field"><label>الموظف <span style="color:var(--primary)">*</span></label><UiSelect v-model="form.employee_id" placeholder="اختر الموظف"><option value="">—</option><option v-for="e in employees" :key="e.id" :value="e.id">{{ e.name }}<template v-if="e.department"> — {{ e.department }}</template></option></UiSelect><small v-if="employee" class="muted">رصيد سنوي {{ employee.annual_leave_days }} يوم</small></div>
        <div class="field"><label>نوع الإجازة</label><UiSelect v-model="form.type"><option v-for="(l, k) in leaveTypes" :key="k" :value="k">{{ l }}</option></UiSelect></div>
        <div class="field"><label>عدد الأيام</label><input :value="days" disabled dir="ltr" /></div>
        <div class="field"><label>من <span style="color:var(--primary)">*</span></label><input v-model="form.from" type="date" dir="ltr" /></div>
        <div class="field"><label>إلى <span style="color:var(--primary)">*</span></label><input v-model="form.to" type="date" dir="ltr" /></div>
        <div class="field"><label>السبب</label><input v-model="form.reason" /></div>
      </div>
      <div class="field"><label>ملاحظات</label><textarea v-model="form.note" rows="2" /></div>
      <p v-if="form.type === 'unpaid'" class="muted" style="font-size:12px"><i class="ti ti-info-circle" /> الإجازة بدون راتب تُخصم تلقائياً من كشف رواتب الشهر عند الموافقة.</p>
      <div class="row" style="gap:10px;margin-top:18px;border-top:1px solid var(--line);padding-top:16px">
        <button class="btn" :disabled="saving || !form.employee_id || !days" @click="save"><i class="ti ti-check" /> تسجيل الطلب</button>
        <NuxtLink class="btn ghost" to="/hr/attendance?tab=leaves">إلغاء</NuxtLink>
      </div>
    </div>
  </div>
</template>
