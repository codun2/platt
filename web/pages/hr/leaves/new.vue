<script setup lang="ts">
definePageMeta({ title: 'طلب إجازة', titleEn: 'New Leave' })
const api = useApi(); const auth = useAuth(); const route = useRoute(); const { tt } = useI18n()
if (!auth.can('hr.manage')) navigateTo('/hr/attendance?tab=leaves')
const employees = ref<any[]>([]); const err = ref(''); const saving = ref(false)
const form = reactive<any>(blankLeave(route.query.employee_id ? String(route.query.employee_id) : ''))
useDraft('leave-new', form)   // kept if the user jumps to an add page from a dropdown
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
    <div class="breadcrumb"><NuxtLink to="/hr/attendance?tab=leaves">{{ tt('الإجازات','Leaves') }}</NuxtLink> › <b>{{ tt('طلب إجازة','New leave') }}</b></div>
    <div class="card">
      <div v-if="err" class="alert bad">{{ err }}</div>
      <div class="card-title"><i class="ti ti-beach" /> {{ tt('بيانات الإجازة','Leave details') }}</div>
      <div class="grid grid-3">
        <div class="field"><label>{{ tt('الموظف','Employee') }} <span style="color:var(--primary)">*</span></label><UiSelect v-model="form.employee_id" :placeholder="tt('اختر الموظف','Select employee')" :add-label="tt('إضافة موظف','Add employee')" add-to="/hr/employees/new" add-key="employee"><option value="">—</option><option v-for="e in employees" :key="e.id" :value="e.id">{{ e.name }}<template v-if="e.department"> — {{ e.department }}</template></option></UiSelect><small v-if="employee" class="muted">{{ tt('رصيد سنوي','Annual balance') }} {{ employee.annual_leave_days }} {{ tt('يوم','days') }}</small></div>
        <div class="field"><label>{{ tt('نوع الإجازة','Leave type') }}</label><UiSelect v-model="form.type"><option v-for="(l, k) in leaveTypes" :key="k" :value="k">{{ l }}</option></UiSelect></div>
        <div class="field"><label>{{ tt('عدد الأيام','Days') }}</label><input :value="days" disabled dir="ltr" /></div>
        <div class="field"><label>{{ tt('من','From') }} <span style="color:var(--primary)">*</span></label><input v-model="form.from" type="date" dir="ltr" /></div>
        <div class="field"><label>{{ tt('إلى','To') }} <span style="color:var(--primary)">*</span></label><input v-model="form.to" type="date" dir="ltr" /></div>
        <div class="field"><label>{{ tt('السبب','Reason') }}</label><input v-model="form.reason" /></div>
      </div>
      <div class="field"><label>{{ tt('ملاحظات','Notes') }}</label><textarea v-model="form.note" rows="2" /></div>
      <p v-if="form.type === 'unpaid'" class="muted" style="font-size:12px"><i class="ti ti-info-circle" /> {{ tt('الإجازة بدون راتب تُخصم تلقائياً من كشف رواتب الشهر عند الموافقة.','Unpaid leave is auto-deducted from the month payroll on approval.') }}</p>
      <div class="row" style="gap:10px;margin-top:18px;border-top:1px solid var(--line);padding-top:16px">
        <button class="btn" :disabled="saving || !form.employee_id || !days" @click="save"><i class="ti ti-check" /> {{ tt('تسجيل الطلب','Submit request') }}</button>
        <NuxtLink class="btn ghost" to="/hr/attendance?tab=leaves">{{ tt('إلغاء','Cancel') }}</NuxtLink>
      </div>
    </div>
  </div>
</template>
