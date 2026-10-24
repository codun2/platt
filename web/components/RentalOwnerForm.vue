<script setup lang="ts">
/** Owner / broker form (new + edit). */
defineProps<{ form: any }>()
</script>

<template>
  <div>
    <div class="card-title"><i class="ti ti-user-star" /> {{ tt('بيانات المالك / الوسيط', 'Owner / broker') }}</div>
    <div class="grid grid-3">
      <div class="field"><label>{{ tt('الاسم', 'Name') }} <span style="color:var(--primary)">*</span></label><input v-model="form.name" /></div>
      <div class="field"><label>{{ tt('الصفة', 'Role') }}</label><UiSelect v-model="form.type"><option value="owner">{{ tt('مالك', 'Owner') }}</option><option value="broker">{{ tt('وسيط', 'Broker') }}</option></UiSelect></div>
      <div class="field"><label>{{ tt('رقم الهوية / السجل', 'ID / CR no.') }}</label><input v-model="form.national_id" dir="ltr" /></div>
      <div class="field"><label>{{ tt('الهاتف', 'Phone') }}</label><input v-model="form.phone" dir="ltr" /></div>
      <div class="field"><label>{{ tt('البريد', 'Email') }}</label><input v-model="form.email" dir="ltr" /></div>
      <div class="field"><label>{{ tt('نسبة الإدارة / العمولة %', 'Management fee / commission %') }}</label><input v-model.number="form.commission_rate" type="number" min="0" max="100" step="0.01" dir="ltr" /><small class="muted">{{ tt('تُخصم من الإيجار المحصّل في كشف المالك', 'Deducted from collected rent in the owner statement') }}</small></div>
    </div>
    <div class="card-title" style="margin-top:8px"><i class="ti ti-building-bank" /> {{ tt('بيانات التحويل', 'Payout details') }}</div>
    <div class="grid grid-2">
      <div class="field"><label>{{ tt('البنك', 'Bank') }}</label><input v-model="form.bank_name" /></div>
      <div class="field"><label>IBAN</label><input v-model="form.iban" dir="ltr" /></div>
    </div>
    <div class="field"><label>{{ tt('ملاحظات', 'Notes') }}</label><textarea v-model="form.notes" rows="2" /></div>
    <label class="switch"><input v-model="form.is_active" type="checkbox" /><span /> {{ tt('نشط', 'Active') }}</label>
  </div>
</template>
