<script setup lang="ts">
definePageMeta({ title: 'ملف الموظف', titleEn: 'Employee Profile' })
const api = useApi(); const auth = useAuth(); const f = useFmt(); const { tt } = useI18n()
const route = useRoute(); const id = Number(route.params.id)
const d = ref<any>(null); const departments = ref<string[]>([]); const users = ref<any[]>([])
const tab = ref<'profile' | 'attendance' | 'leaves' | 'payslips'>('profile')
const editing = ref(route.query.edit === '1'); const err = ref(''); const msg = ref(''); const saving = ref(false); const notFound = ref(false)
const form = reactive<any>(blankEmployee())
const e = computed(() => d.value?.employee)
const canManage = computed(() => auth.can('hr.manage'))
const yearsOfService = computed(() => e.value?.hire_date ? Math.floor(-(daysUntil(e.value.hire_date) || 0) / 365) : null)

async function load() {
  try { d.value = await api.get(`/app/hr/employees/${id}`) } catch (x: any) { if (x.status === 404) { notFound.value = true; return } throw x }
  Object.assign(form, employeeToForm(d.value.employee))
}
onMounted(async () => { await load(); const r = await api.get('/app/hr/employees', { all: 1 }); departments.value = r.stats?.departments || []; try { users.value = await api.get('/app/users') } catch { users.value = [] } })
async function save() {
  err.value = ''; msg.value = ''; saving.value = true
  try { await api.patch(`/app/hr/employees/${id}`, employeePayload(form)); msg.value = tt('تم حفظ التعديلات','Changes saved'); editing.value = false; await load() }
  catch (x: any) { err.value = x.message } finally { saving.value = false; window.scrollTo({ top: 0, behavior: 'smooth' }) }
}
async function remove() { if (!confirm(tt(`حذف الموظف ${e.value.name}؟`,`Delete employee ${e.value.name}?`))) return; try { const r = await api.del(`/app/hr/employees/${id}`); if (r.deactivated) { msg.value = r.message; await load() } else navigateTo('/hr/employees') } catch (x: any) { err.value = x.message } }
</script>

<template>
  <div v-if="e">
    <div class="breadcrumb"><NuxtLink to="/hr/employees">{{ tt('الموظفون','Employees') }}</NuxtLink> › <b>{{ e.name }}</b></div>
    <div v-if="msg" class="alert ok">{{ msg }}</div>
    <div v-if="err" class="alert bad">{{ err }}</div>

    <div v-if="editing" class="card">
      <EmployeeForm :form="form" :departments="departments" :users="users" />
      <div class="row" style="gap:10px;margin-top:18px;border-top:1px solid var(--line);padding-top:16px">
        <button class="btn" :disabled="saving" @click="save"><i class="ti ti-check" /> {{ tt('حفظ التعديلات','Save changes') }}</button>
        <button class="btn ghost" :disabled="saving" @click="editing = false; load()">{{ tt('إلغاء','Cancel') }}</button>
      </div>
    </div>

    <template v-else>
      <div class="grid grid-4" style="margin-bottom:16px">
        <div class="card stat"><div class="ic" style="background:var(--info)"><i class="ti ti-cash" /></div><div><b>{{ f.money(e.gross_salary) }}</b><small>الراتب الإجمالي · صافي {{ f.money(e.gross_salary - +e.fixed_deductions) }}</small></div></div>
        <div class="card stat"><div class="ic" style="background:var(--ok)"><i class="ti ti-calendar-check" /></div><div><b>{{ d.attendance_month.present }} / {{ d.attendance_month.absent }}</b><small>حضور / غياب هذا الشهر · تأخير {{ d.attendance_month.late_minutes }} د</small></div></div>
        <div class="card stat"><div class="ic" style="background:var(--warn)"><i class="ti ti-beach" /></div><div><b>{{ d.leave_balance.remaining }} / {{ d.leave_balance.annual }}</b><small>رصيد الإجازة السنوية المتبقي</small></div></div>
        <div class="card stat"><div class="ic" style="background:var(--primary)"><i class="ti ti-briefcase" /></div><div><b>{{ yearsOfService !== null ? yearsOfService + ' سنة' : '—' }}</b><small>مدة الخدمة · منذ {{ f.d(e.hire_date) }}</small></div></div>
      </div>

      <div class="card">
        <div class="row between" style="margin-bottom:4px">
          <div class="tabs" style="margin-bottom:0;border:none">
            <button :class="{ on: tab === 'profile' }" @click="tab = 'profile'">{{ tt('الملف','Profile') }}</button>
            <button :class="{ on: tab === 'attendance' }" @click="tab = 'attendance'">{{ tt('الحضور','Attendance') }} ({{ d.attendance.length }})</button>
            <button :class="{ on: tab === 'leaves' }" @click="tab = 'leaves'">{{ tt('الإجازات','Leaves') }} ({{ d.leaves.length }})</button>
            <button :class="{ on: tab === 'payslips' }" @click="tab = 'payslips'">{{ tt('الرواتب','Payroll') }} ({{ d.payslips.length }})</button>
          </div>
          <div class="row" style="gap:8px">
            <span class="badge" :class="employeeStatus[e.status]?.cls">{{ employeeStatus[e.status]?.label }}</span>
            <template v-if="canManage"><NuxtLink class="btn sm" :to="`/hr/leaves/new?employee_id=${e.id}`"><i class="ti ti-plus" /> طلب إجازة</NuxtLink><button class="btn ghost sm" @click="editing = true"><i class="ti ti-pencil" /> تعديل</button><button class="btn ghost sm" style="color:var(--primary)" @click="remove"><i class="ti ti-trash" /></button></template>
          </div>
        </div>
        <hr style="border:none;border-top:1px solid var(--line);margin:0 0 16px">

        <div v-if="tab === 'profile'" class="grid grid-3">
          <div><small class="muted">{{ tt('الاسم','Name') }}</small><br /><b>{{ e.name }}</b><br /><small class="muted">{{ e.name_en }}</small></div>
          <div><small class="muted">{{ tt('الرقم الوظيفي','Employee ID') }}</small><br /><b dir="ltr">{{ e.code || '—' }}</b></div>
          <div><small class="muted">{{ tt('المسمى / القسم','Title / dept') }}</small><br /><b>{{ [e.job_title, e.department].filter(Boolean).join(' · ') || '—' }}</b></div>
          <div><small class="muted">{{ tt('نوع التوظيف','Employment type') }}</small><br /><b>{{ employmentTypes[e.employment_type] }}</b></div>
          <div><small class="muted">{{ tt('التعيين / الانتهاء','Hire / end') }}</small><br /><b>{{ f.d(e.hire_date) }} → {{ f.d(e.end_date) }}</b></div>
          <div><small class="muted">{{ tt('الهاتف / البريد','Phone / email') }}</small><br /><b dir="ltr">{{ [e.phone, e.email].filter(Boolean).join(' · ') || '—' }}</b></div>
          <div><small class="muted">{{ tt('الهوية / الجنسية','ID / nationality') }}</small><br /><b>{{ [e.national_id, e.nationality].filter(Boolean).join(' · ') || '—' }}</b></div>
          <div><small class="muted">{{ tt('الميلاد / الجنس / الحالة','DOB / gender / marital') }}</small><br /><b>{{ [f.d(e.birth_date), e.gender === 'male' ? 'ذكر' : e.gender === 'female' ? 'أنثى' : '', maritalStatus[e.marital_status]].filter(Boolean).join(' · ') }}</b></div>
          <div><small class="muted">{{ tt('العنوان','Address') }}</small><br /><b>{{ e.address || '—' }}</b></div>
          <div><small class="muted">{{ tt('الراتب الأساسي','Basic salary') }}</small><br /><b>{{ f.money(e.basic_salary) }}</b></div>
          <div><small class="muted">{{ tt('البدلات (سكن/نقل/أخرى)','Allowances (housing/transport/other)') }}</small><br /><b>{{ f.money(e.housing_allowance) }} / {{ f.money(e.transport_allowance) }} / {{ f.money(e.other_allowance) }}</b></div>
          <div><small class="muted">{{ tt('استقطاعات ثابتة','Fixed deductions') }}</small><br /><b>{{ f.money(e.fixed_deductions) }}</b></div>
          <div><small class="muted">{{ tt('البنك / IBAN','Bank / IBAN') }}</small><br /><b dir="ltr">{{ [e.bank_name, e.iban].filter(Boolean).join(' · ') || '—' }}</b></div>
          <div><small class="muted">{{ tt('طوارئ','Emergency') }}</small><br /><b dir="ltr">{{ [e.emergency_name, e.emergency_phone].filter(Boolean).join(' · ') || '—' }}</b></div>
          <div><small class="muted">{{ tt('حساب النظام','System account') }}</small><br /><b>{{ e.user?.name || '—' }}</b></div>
          <div v-if="e.notes" style="grid-column:1/-1;background:var(--bg);padding:10px;border-radius:10px"><small class="muted">ملاحظات</small><br />{{ e.notes }}</div>
        </div>

        <div v-else-if="tab === 'attendance'">
          <table class="table"><thead><tr><th>{{ tt('التاريخ','Date') }}</th><th>{{ tt('الحالة','Status') }}</th><th>{{ tt('دخول','In') }}</th><th>{{ tt('خروج','Out') }}</th><th>{{ tt('تأخير','Late') }}</th><th>{{ tt('إضافي','OT') }}</th><th>{{ tt('ملاحظة','Note') }}</th></tr></thead>
            <tbody><tr v-for="a in d.attendance" :key="a.id"><td>{{ f.d(a.date) }}</td><td><span class="badge" :class="attendanceStatus[a.status]?.cls">{{ attendanceStatus[a.status]?.label }}</span></td><td dir="ltr">{{ a.check_in || '—' }}</td><td dir="ltr">{{ a.check_out || '—' }}</td><td>{{ a.late_minutes ? a.late_minutes + ' د' : '' }}</td><td>{{ a.overtime_minutes ? a.overtime_minutes + ' د' : '' }}</td><td class="muted">{{ a.note || '' }}</td></tr></tbody></table>
          <p v-if="!d.attendance.length" class="empty">{{ tt('لا توجد سجلات حضور.','No attendance records.') }}</p>
        </div>

        <div v-else-if="tab === 'leaves'">
          <table class="table"><thead><tr><th>{{ tt('النوع','Type') }}</th><th>{{ tt('من','From') }}</th><th>{{ tt('إلى','To') }}</th><th>{{ tt('أيام','Days') }}</th><th>{{ tt('السبب','Reason') }}</th><th>{{ tt('الحالة','Status') }}</th></tr></thead>
            <tbody><tr v-for="l in d.leaves" :key="l.id"><td><NuxtLink :to="`/hr/leaves/${l.id}`" style="color:var(--info);font-weight:700">{{ leaveTypes[l.type] }}</NuxtLink></td><td>{{ f.d(l.from) }}</td><td>{{ f.d(l.to) }}</td><td>{{ l.days }}</td><td class="muted">{{ l.reason || '' }}</td><td><span class="badge" :class="leaveStatus[l.status]?.cls">{{ leaveStatus[l.status]?.label }}</span></td></tr></tbody></table>
          <p v-if="!d.leaves.length" class="empty">{{ tt('لا توجد إجازات.','No leaves.') }}</p>
        </div>

        <div v-else>
          <table class="table"><thead><tr><th>{{ tt('الشهر','Month') }}</th><th>{{ tt('الأساسي','Basic') }}</th><th>{{ tt('البدلات','Allow.') }}</th><th>{{ tt('إضافات','Additions') }}</th><th>{{ tt('خصومات','Deductions') }}</th><th>{{ tt('الصافي','Net') }}</th><th>{{ tt('الحالة','Status') }}</th></tr></thead>
            <tbody><tr v-for="p in d.payslips" :key="p.id"><td><NuxtLink :to="`/hr/payroll/${p.payroll?.id}`" style="color:var(--info);font-weight:700">{{ p.payroll ? monthLabel(p.payroll.period) : '—' }}</NuxtLink></td><td class="num">{{ f.money(p.basic) }}</td><td class="num">{{ f.money(p.allowances) }}</td><td class="num">{{ f.money(+p.overtime + +p.bonus) }}</td><td class="num">{{ f.money(+p.absence_deduction + +p.fixed_deductions + +p.other_deductions + +p.advance) }}</td><td class="num"><b>{{ f.money(p.net) }}</b></td><td><span class="badge" :class="payrollStatus[p.payroll?.status]?.cls">{{ payrollStatus[p.payroll?.status]?.label }}</span></td></tr></tbody></table>
          <p v-if="!d.payslips.length" class="empty">{{ tt('لا توجد كشوف رواتب.','No payslips.') }}</p>
        </div>
      </div>
    </template>
  </div>
  <div v-else-if="notFound" class="card" style="text-align:center;padding:50px 20px"><h2>{{ tt('الموظف غير موجود','Employee not found') }}</h2><NuxtLink to="/hr/employees" class="btn ghost mt">{{ tt('العودة','Back') }}</NuxtLink></div>
</template>
