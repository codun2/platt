<script setup lang="ts">
/** Full employee profile form (new + edit). */
const props = defineProps<{ form: any; departments: string[]; users: any[] }>()
const f = useFmt()
const gross = computed(() => grossOf(props.form))
const net = computed(() => Math.round((gross.value - (+props.form.fixed_deductions || 0)) * 1000) / 1000)
</script>

<template>
  <div>
    <div class="card-title"><i class="ti ti-id-badge-2" /> البيانات الوظيفية</div>
    <div class="grid grid-3">
      <div class="field"><label>اسم الموظف <span style="color:var(--primary)">*</span></label><input v-model="form.name" /></div>
      <div class="field"><label>الاسم بالإنجليزية</label><input v-model="form.name_en" dir="ltr" /></div>
      <div class="field"><label>الرقم الوظيفي</label><input v-model="form.code" dir="ltr" placeholder="اختياري" /></div>
      <div class="field"><label>المسمى الوظيفي</label><input v-model="form.job_title" /></div>
      <div class="field"><label>القسم</label><input v-model="form.department" list="dept-list" placeholder="المالية، المبيعات…" /><datalist id="dept-list"><option v-for="d in departments" :key="d" :value="d" /></datalist></div>
      <div class="field"><label>نوع التوظيف</label><UiSelect v-model="form.employment_type"><option v-for="(l, k) in employmentTypes" :key="k" :value="k">{{ l }}</option></UiSelect></div>
      <div class="field"><label>تاريخ التعيين</label><input v-model="form.hire_date" type="date" dir="ltr" /></div>
      <div class="field"><label>تاريخ انتهاء الخدمة</label><input v-model="form.end_date" type="date" dir="ltr" /></div>
      <div class="field"><label>الحالة</label><UiSelect v-model="form.status"><option v-for="(s, k) in employeeStatus" :key="k" :value="k">{{ s.label }}</option></UiSelect></div>
      <div class="field"><label>حساب الدخول للنظام (اختياري)</label><UiSelect v-model="form.user_id" placeholder="—"><option value="">—</option><option v-for="u in users" :key="u.id" :value="u.id">{{ u.name }} — {{ u.email }}</option></UiSelect></div>
      <div class="field"><label>رصيد الإجازة السنوية (يوم)</label><input v-model.number="form.annual_leave_days" type="number" min="0" max="365" dir="ltr" /></div>
    </div>

    <div class="card-title" style="margin-top:8px"><i class="ti ti-user" /> البيانات الشخصية</div>
    <div class="grid grid-4">
      <div class="field"><label>الهاتف</label><input v-model="form.phone" dir="ltr" /></div>
      <div class="field"><label>البريد</label><input v-model="form.email" dir="ltr" /></div>
      <div class="field"><label>رقم الهوية / الإقامة</label><input v-model="form.national_id" dir="ltr" /></div>
      <div class="field"><label>الجنسية</label><input v-model="form.nationality" /></div>
      <div class="field"><label>تاريخ الميلاد</label><input v-model="form.birth_date" type="date" dir="ltr" /></div>
      <div class="field"><label>الجنس</label><UiSelect v-model="form.gender"><option value="">—</option><option value="male">ذكر</option><option value="female">أنثى</option></UiSelect></div>
      <div class="field"><label>الحالة الاجتماعية</label><UiSelect v-model="form.marital_status"><option value="">—</option><option v-for="(l, k) in maritalStatus" :key="k" :value="k">{{ l }}</option></UiSelect></div>
      <div class="field"><label>العنوان</label><input v-model="form.address" /></div>
      <div class="field"><label>جهة اتصال للطوارئ</label><input v-model="form.emergency_name" /></div>
      <div class="field"><label>هاتف الطوارئ</label><input v-model="form.emergency_phone" dir="ltr" /></div>
    </div>

    <div class="card-title" style="margin-top:8px"><i class="ti ti-cash" /> الراتب والبدلات</div>
    <div class="grid grid-2" style="align-items:start">
      <div class="grid grid-2">
        <div class="field"><label>الراتب الأساسي</label><input v-model.number="form.basic_salary" type="number" step="0.001" min="0" dir="ltr" /></div>
        <div class="field"><label>بدل سكن</label><input v-model.number="form.housing_allowance" type="number" step="0.001" min="0" dir="ltr" /></div>
        <div class="field"><label>بدل نقل</label><input v-model.number="form.transport_allowance" type="number" step="0.001" min="0" dir="ltr" /></div>
        <div class="field"><label>بدلات أخرى</label><input v-model.number="form.other_allowance" type="number" step="0.001" min="0" dir="ltr" /></div>
        <div class="field"><label>استقطاعات ثابتة (تأمينات…)</label><input v-model.number="form.fixed_deductions" type="number" step="0.001" min="0" dir="ltr" /></div>
      </div>
      <div class="totals-box">
        <div class="row between"><span>الأساسي</span><b>{{ f.money(form.basic_salary) }}</b></div>
        <div class="row between"><span>البدلات</span><b>{{ f.money(gross - (+form.basic_salary || 0)) }}</b></div>
        <div class="row between"><span>الإجمالي</span><b>{{ f.money(gross) }}</b></div>
        <div class="row between"><span>الاستقطاعات الثابتة</span><b>- {{ f.money(form.fixed_deductions) }}</b></div>
        <div class="row between total"><span>الصافي الشهري المتوقع</span><b>{{ f.money(net, true) }}</b></div>
        <small class="muted">الأجر اليومي للخصم: {{ f.money(gross / 30) }} (الإجمالي ÷ 30)</small>
      </div>
    </div>

    <div class="card-title" style="margin-top:8px"><i class="ti ti-building-bank" /> البيانات البنكية</div>
    <div class="grid grid-2">
      <div class="field"><label>البنك</label><input v-model="form.bank_name" /></div>
      <div class="field"><label>IBAN</label><input v-model="form.iban" dir="ltr" /></div>
    </div>
    <div class="field"><label>ملاحظات</label><textarea v-model="form.notes" rows="2" /></div>
  </div>
</template>
