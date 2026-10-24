<script setup lang="ts">
/** Case (law) / project (engineering) form — new + edit. */
const props = defineProps<{ form: any; customers: any[]; people: any[] }>()
const terms = computed(() => projectTerms(props.form.kind))
// Won / lost / settled only make sense for court cases.
const statuses = computed(() => Object.keys(projectStatus).filter(k => terms.value.isCase || !['won', 'lost', 'settled'].includes(k)))
</script>

<template>
  <div>
    <div class="card-title"><i :class="`ti ${terms.icon}`" /> {{ tt('البيانات الأساسية', 'Basics') }}</div>
    <div class="grid grid-3">
      <div class="field" style="grid-column:span 2"><label>{{ terms.isCase ? tt('موضوع القضية', 'Case subject') : tt('اسم المشروع', 'Project name') }} <span style="color:var(--primary)">*</span></label><input v-model="form.title" :placeholder="terms.isCase ? tt('مثال: مطالبة مالية ضد…', 'e.g. Payment claim against…') : tt('مثال: تصميم فيلا سكنية — حي النرجس', 'e.g. Villa design — Narjis')" /></div>
      <div class="field"><label>{{ terms.client }}</label><UiSelect v-model="form.customer_id" :add-label="terms.isCase ? tt('إضافة موكّل', 'Add client') : tt('إضافة عميل', 'Add client')" add-to="/crm/customers/new" add-key="customer"><option value="">—</option><option v-for="c in customers" :key="c.id" :value="c.id">{{ c.name }}<template v-if="c.phone"> — {{ c.phone }}</template></option></UiSelect></div>
      <div class="field"><label>{{ tt('الحالة', 'Status') }}</label><UiSelect v-model="form.status"><option v-for="k in statuses" :key="k" :value="k">{{ projectStatus[k].label }}</option></UiSelect></div>
      <div class="field"><label>{{ tt('الأولوية', 'Priority') }}</label><UiSelect v-model="form.priority"><option v-for="(s, k) in priorities" :key="k" :value="k">{{ s.label }}</option></UiSelect></div>
      <div class="field"><label>{{ terms.lead }}</label><UiSelect v-model="form.lead_id"><option value="">—</option><option v-for="u in people" :key="u.id" :value="u.id">{{ u.name }}</option></UiSelect></div>
      <div class="field"><label>{{ terms.isCase ? tt('تاريخ القيد / الاستلام', 'Filed / received on') : tt('تاريخ البدء', 'Start date') }}</label><input v-model="form.start_date" type="date" dir="ltr" /></div>
      <div class="field"><label>{{ terms.isCase ? tt('موعد نهائي (اختياري)', 'Deadline (optional)') : tt('موعد التسليم', 'Due date') }}</label><input v-model="form.due_date" type="date" dir="ltr" /></div>
    </div>

    <template v-if="terms.isCase">
      <div class="card-title" style="margin-top:8px"><i class="ti ti-building-bank" /> {{ tt('بيانات الدعوى', 'Court details') }}</div>
      <div class="grid grid-3">
        <div class="field"><label>{{ tt('نوع القضية', 'Case type') }}</label><UiSelect v-model="form.case_type"><option v-for="(l, k) in caseTypes" :key="k" :value="k">{{ l }}</option></UiSelect></div>
        <div class="field"><label>{{ tt('المرحلة', 'Stage') }}</label><UiSelect v-model="form.stage"><option v-for="(l, k) in caseStages" :key="k" :value="k">{{ l }}</option></UiSelect></div>
        <div class="field"><label>{{ tt('صفة الموكّل', 'Client role') }}</label><UiSelect v-model="form.client_role"><option v-for="(l, k) in clientRoles" :key="k" :value="k">{{ l }}</option></UiSelect></div>
        <div class="field"><label>{{ tt('المحكمة / الجهة', 'Court') }}</label><input v-model="form.court" :placeholder="tt('المحكمة التجارية بالرياض', 'Commercial Court, Riyadh')" /></div>
        <div class="field"><label>{{ tt('رقم القضية', 'Case number') }}</label><input v-model="form.court_case_no" dir="ltr" /></div>
        <div class="field"><label>{{ tt('الخصم', 'Opponent') }}</label><input v-model="form.opponent" /></div>
        <div class="field"><label>{{ tt('محامي الخصم', 'Opposing counsel') }}</label><input v-model="form.opponent_lawyer" /></div>
      </div>
    </template>
    <template v-else>
      <div class="card-title" style="margin-top:8px"><i class="ti ti-map-pin" /> {{ tt('الموقع والقيمة', 'Site & value') }}</div>
      <div class="grid grid-3">
        <div class="field"><label>{{ tt('الموقع', 'Location') }}</label><input v-model="form.location" /></div>
        <div class="field"><label>{{ tt('قيمة العقد', 'Contract value') }}</label><input v-model.number="form.contract_value" type="number" min="0" step="0.001" dir="ltr" /></div>
        <div class="field"><label>{{ tt('نسبة الإنجاز %', 'Progress %') }}</label><input v-model.number="form.progress" type="number" min="0" max="100" dir="ltr" /></div>
      </div>
    </template>

    <div class="card-title" style="margin-top:8px"><i class="ti ti-coins" /> {{ tt('الأتعاب', 'Fees') }}</div>
    <div class="grid grid-3">
      <div class="field"><label>{{ tt('طريقة الأتعاب', 'Fee arrangement') }}</label><UiSelect v-model="form.fee_type"><option v-for="(l, k) in feeTypes" :key="k" :value="k">{{ l }}</option></UiSelect></div>
      <div class="field"><label>{{ tt('سعر الساعة', 'Hourly rate') }}</label><input v-model.number="form.hourly_rate" type="number" min="0" step="0.001" dir="ltr" /><small class="muted">{{ tt('يُستخدم لحساب قيمة الساعات المسجّلة', 'Used to value logged hours') }}</small></div>
      <div v-if="form.fee_type !== 'hourly'" class="field"><label>{{ form.fee_type === 'fixed' ? tt('مبلغ الأتعاب المتفق عليه', 'Agreed fee') : tt('مبلغ الاشتراك الدوري', 'Retainer amount') }}</label><input v-model.number="form.fee_amount" type="number" min="0" step="0.001" dir="ltr" /></div>
    </div>
    <div class="grid grid-2">
      <div class="field"><label>{{ terms.isCase ? tt('ملخّص الوقائع', 'Facts summary') : tt('وصف المشروع', 'Description') }}</label><textarea v-model="form.description" rows="3" /></div>
      <div class="field"><label>{{ tt('ملاحظات داخلية', 'Internal notes') }}</label><textarea v-model="form.notes" rows="3" /></div>
    </div>
  </div>
</template>
