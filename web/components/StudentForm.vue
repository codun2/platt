<script setup lang="ts">
/** Student form (new + edit) with inline guardian creation. */
const props = defineProps<{ form: any; classes: any[]; guardians: any[] }>()
const newGuardian = ref(!props.form.guardian_id)
watch(newGuardian, v => { if (v) props.form.guardian_id = '' })
// A guardian chosen elsewhere ("save & add sibling") switches to the existing-guardian view.
watch(() => props.form.guardian_id, v => { if (v) newGuardian.value = false })
</script>

<template>
  <div>
    <div class="card-title"><i class="ti ti-user" /> {{ tt('بيانات الطالب', 'Student') }}</div>
    <div class="grid grid-3">
      <div class="field"><label>{{ tt('الاسم الرباعي', 'Full name') }} <span style="color:var(--primary)">*</span></label><input v-model="form.name" /></div>
      <div class="field"><label>{{ tt('الاسم بالإنجليزية', 'Name (English)') }}</label><input v-model="form.name_en" dir="ltr" /></div>
      <div class="field"><label>{{ tt('رقم الطالب', 'Student no.') }}</label><input v-model="form.student_no" dir="ltr" :placeholder="tt('تلقائي', 'Auto')" /></div>
      <div class="field"><label>{{ tt('الجنس', 'Gender') }}</label><UiSelect v-model="form.gender"><option value="">—</option><option value="male">{{ tt('ذكر', 'Male') }}</option><option value="female">{{ tt('أنثى', 'Female') }}</option></UiSelect></div>
      <div class="field"><label>{{ tt('تاريخ الميلاد', 'Date of birth') }}</label><input v-model="form.birth_date" type="date" dir="ltr" /></div>
      <div class="field"><label>{{ tt('رقم الهوية / الإقامة', 'ID / residency') }}</label><input v-model="form.national_id" dir="ltr" /></div>
      <div class="field"><label>{{ tt('الجنسية', 'Nationality') }}</label><input v-model="form.nationality" /></div>
      <div class="field"><label>{{ tt('الصف والشعبة', 'Class') }}</label><UiSelect v-model="form.class_id"><option value="">—</option><option v-for="c in classes" :key="c.id" :value="c.id">{{ c.name }}</option></UiSelect></div>
      <div class="field"><label>{{ tt('تاريخ الالتحاق', 'Enrolled on') }}</label><input v-model="form.enrolled_at" type="date" dir="ltr" /></div>
    </div>

    <div class="card-title" style="margin-top:8px"><i class="ti ti-users-group" /> {{ tt('ولي الأمر', 'Guardian') }}</div>
    <div class="row" style="gap:6px;margin-bottom:8px"><span class="chip" :class="{ on: newGuardian }" @click="newGuardian = true">{{ tt('ولي أمر جديد', 'New guardian') }}</span><span class="chip" :class="{ on: !newGuardian }" @click="newGuardian = false">{{ tt('مسجّل (أخ / أخت بالمدرسة)', 'Existing (sibling)') }}</span></div>
    <div class="grid grid-3">
      <template v-if="newGuardian">
        <div class="field"><label>{{ tt('اسم ولي الأمر', 'Guardian name') }}</label><input v-model="form.new_guardian.name" /></div>
        <div class="field"><label>{{ tt('جوال ولي الأمر', 'Guardian mobile') }}</label><input v-model="form.new_guardian.phone" dir="ltr" /><small class="muted">{{ tt('إن كان مسجّلاً بنفس الجوال يُربط تلقائياً', 'Linked automatically if already registered') }}</small></div>
      </template>
      <div v-else class="field" style="grid-column:span 2"><label>{{ tt('ولي الأمر', 'Guardian') }}</label><UiSelect v-model="form.guardian_id" :placeholder="tt('اختر', 'Select')"><option value="">—</option><option v-for="g in guardians" :key="g.id" :value="g.id">{{ g.name }}<template v-if="g.phone"> — {{ g.phone }}</template></option></UiSelect></div>
      <div class="field"><label>{{ tt('صلة القرابة', 'Relation') }}</label><UiSelect v-model="form.guardian_relation"><option v-for="(l, k) in guardianRelations" :key="k" :value="k">{{ l }}</option></UiSelect></div>
      <div class="field"><label>{{ tt('هاتف طوارئ', 'Emergency phone') }}</label><input v-model="form.emergency_phone" dir="ltr" /></div>
    </div>

    <div class="card-title" style="margin-top:8px"><i class="ti ti-adjustments" /> {{ tt('الرسوم والخدمات', 'Fees & services') }}</div>
    <div class="grid grid-3">
      <div class="field"><label>{{ tt('خصم على الرسوم %', 'Fee discount %') }}</label><input v-model.number="form.discount_pct" type="number" min="0" max="100" step="0.5" dir="ltr" /><small class="muted">{{ tt('خصم إخوة / منحة — يُطبَّق عند إصدار الأقساط', 'Sibling / scholarship — applied when fees are issued') }}</small></div>
      <div class="field"><label>{{ tt('النقل (خط الحافلة)', 'Transport (bus route)') }}</label><input v-model="form.transport" /></div>
      <div class="field"><label>{{ tt('الحالة', 'Status') }}</label><UiSelect v-model="form.status"><option v-for="(s, k) in studentStatus" :key="k" :value="k">{{ s.label }}</option></UiSelect></div>
    </div>
    <div class="grid grid-2">
      <div class="field"><label>{{ tt('ملاحظات صحية', 'Medical notes') }}</label><textarea v-model="form.medical_notes" rows="2" :placeholder="tt('حساسية، أدوية…', 'Allergies, medication…')" /></div>
      <div class="field"><label>{{ tt('ملاحظات', 'Notes') }}</label><textarea v-model="form.notes" rows="2" /></div>
    </div>
  </div>
</template>
