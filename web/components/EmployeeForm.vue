<script setup lang="ts">
/** Full employee profile form (new + edit). */
const props = defineProps<{ form: any; departments: string[]; users: any[] }>()
const f = useFmt(); const { tt } = useI18n()
const gross = computed(() => grossOf(props.form))
const net = computed(() => Math.round((gross.value - (+props.form.fixed_deductions || 0)) * 1000) / 1000)
</script>

<template>
  <div>
    <div class="card-title"><i class="ti ti-id-badge-2" /> {{ tt('البيانات الوظيفية','Employment info') }}</div>
    <div class="grid grid-3">
      <div class="field"><label>{{ tt('اسم الموظف','Employee name') }} <span style="color:var(--primary)">*</span></label><input v-model="form.name" /></div>
      <div class="field"><label>{{ tt('الاسم بالإنجليزية','Name (English)') }}</label><input v-model="form.name_en" dir="ltr" /></div>
      <div class="field"><label>{{ tt('الرقم الوظيفي','Employee ID') }}</label><input v-model="form.code" dir="ltr" :placeholder="tt('اختياري','Optional')" /></div>
      <div class="field"><label>{{ tt('المسمى الوظيفي','Job title') }}</label><input v-model="form.job_title" /></div>
      <div class="field"><label>{{ tt('القسم','Department') }}</label><input v-model="form.department" list="dept-list" :placeholder="tt('المالية، المبيعات…','Finance, Sales…')" /><datalist id="dept-list"><option v-for="d in departments" :key="d" :value="d" /></datalist></div>
      <div class="field"><label>{{ tt('نوع التوظيف','Employment type') }}</label><UiSelect v-model="form.employment_type"><option v-for="(l, k) in employmentTypes" :key="k" :value="k">{{ l }}</option></UiSelect></div>
      <div class="field"><label>{{ tt('تاريخ التعيين','Hire date') }}</label><input v-model="form.hire_date" type="date" dir="ltr" /></div>
      <div class="field"><label>{{ tt('تاريخ انتهاء الخدمة','End date') }}</label><input v-model="form.end_date" type="date" dir="ltr" /></div>
      <div class="field"><label>{{ tt('الحالة','Status') }}</label><UiSelect v-model="form.status"><option v-for="(s, k) in employeeStatus" :key="k" :value="k">{{ s.label }}</option></UiSelect></div>
      <div class="field"><label>{{ tt('حساب الدخول للنظام (اختياري)','System login (optional)') }}</label><UiSelect v-model="form.user_id" placeholder="—"><option value="">—</option><option v-for="u in users" :key="u.id" :value="u.id">{{ u.name }} — {{ u.email }}</option></UiSelect></div>
      <div class="field"><label>{{ tt('رصيد الإجازة السنوية (يوم)','Annual leave balance (days)') }}</label><input v-model.number="form.annual_leave_days" type="number" min="0" max="365" dir="ltr" /></div>
    </div>

    <div class="card-title" style="margin-top:8px"><i class="ti ti-user" /> {{ tt('البيانات الشخصية','Personal info') }}</div>
    <div class="grid grid-4">
      <div class="field"><label>{{ tt('الهاتف','Phone') }}</label><input v-model="form.phone" dir="ltr" /></div>
      <div class="field"><label>{{ tt('البريد','Email') }}</label><input v-model="form.email" dir="ltr" /></div>
      <div class="field"><label>{{ tt('رقم الهوية / الإقامة','ID / residency no.') }}</label><input v-model="form.national_id" dir="ltr" /></div>
      <div class="field"><label>{{ tt('الجنسية','Nationality') }}</label><input v-model="form.nationality" /></div>
      <div class="field"><label>{{ tt('تاريخ الميلاد','Date of birth') }}</label><input v-model="form.birth_date" type="date" dir="ltr" /></div>
      <div class="field"><label>{{ tt('الجنس','Gender') }}</label><UiSelect v-model="form.gender"><option value="">—</option><option value="male">{{ tt('ذكر','Male') }}</option><option value="female">{{ tt('أنثى','Female') }}</option></UiSelect></div>
      <div class="field"><label>{{ tt('الحالة الاجتماعية','Marital status') }}</label><UiSelect v-model="form.marital_status"><option value="">—</option><option v-for="(l, k) in maritalStatus" :key="k" :value="k">{{ l }}</option></UiSelect></div>
      <div class="field"><label>{{ tt('العنوان','Address') }}</label><input v-model="form.address" /></div>
      <div class="field"><label>{{ tt('جهة اتصال للطوارئ','Emergency contact') }}</label><input v-model="form.emergency_name" /></div>
      <div class="field"><label>{{ tt('هاتف الطوارئ','Emergency phone') }}</label><input v-model="form.emergency_phone" dir="ltr" /></div>
    </div>

    <div class="card-title" style="margin-top:8px"><i class="ti ti-cash" /> {{ tt('الراتب والبدلات','Salary & allowances') }}</div>
    <div class="grid grid-2" style="align-items:start">
      <div class="grid grid-2">
        <div class="field"><label>{{ tt('الراتب الأساسي','Basic salary') }}</label><input v-model.number="form.basic_salary" type="number" step="0.001" min="0" dir="ltr" /></div>
        <div class="field"><label>{{ tt('بدل سكن','Housing') }}</label><input v-model.number="form.housing_allowance" type="number" step="0.001" min="0" dir="ltr" /></div>
        <div class="field"><label>{{ tt('بدل نقل','Transport') }}</label><input v-model.number="form.transport_allowance" type="number" step="0.001" min="0" dir="ltr" /></div>
        <div class="field"><label>{{ tt('بدلات أخرى','Other allowances') }}</label><input v-model.number="form.other_allowance" type="number" step="0.001" min="0" dir="ltr" /></div>
        <div class="field"><label>{{ tt('استقطاعات ثابتة (تأمينات…)','Fixed deductions (insurance…)') }}</label><input v-model.number="form.fixed_deductions" type="number" step="0.001" min="0" dir="ltr" /></div>
      </div>
      <div class="totals-box">
        <div class="row between"><span>{{ tt('الأساسي','Basic') }}</span><b>{{ f.money(form.basic_salary) }}</b></div>
        <div class="row between"><span>{{ tt('البدلات','Allowances') }}</span><b>{{ f.money(gross - (+form.basic_salary || 0)) }}</b></div>
        <div class="row between"><span>{{ tt('الإجمالي','Total') }}</span><b>{{ f.money(gross) }}</b></div>
        <div class="row between"><span>{{ tt('الاستقطاعات الثابتة','Fixed deductions') }}</span><b>- {{ f.money(form.fixed_deductions) }}</b></div>
        <div class="row between total"><span>{{ tt('الصافي الشهري المتوقع','Expected monthly net') }}</span><b>{{ f.money(net, true) }}</b></div>
        <small class="muted">{{ tt('الأجر اليومي للخصم:','Daily rate:') }} {{ f.money(gross / 30) }} ({{ tt('الإجمالي ÷ 30','gross ÷ 30') }})</small>
      </div>
    </div>

    <div class="card-title" style="margin-top:8px"><i class="ti ti-building-bank" /> {{ tt('البيانات البنكية','Bank details') }}</div>
    <div class="grid grid-2">
      <div class="field"><label>{{ tt('البنك','Bank') }}</label><input v-model="form.bank_name" /></div>
      <div class="field"><label>IBAN</label><input v-model="form.iban" dir="ltr" /></div>
    </div>
    <div class="field"><label>{{ tt('ملاحظات','Notes') }}</label><textarea v-model="form.notes" rows="2" /></div>
  </div>
</template>
