<script setup lang="ts">
/** Full supplier profile form (new + edit). */
const props = defineProps<{ form: any }>()
const { tt } = useI18n()
const isCompany = computed(() => props.form.supplier_type === 'company')
</script>

<template>
  <div>
    <div class="card-title"><i class="ti ti-truck" /> {{ tt('بيانات أساسية','Basic info') }}</div>
    <div class="grid grid-3">
      <div class="field"><label>{{ tt('نوع المورد','Supplier type') }}</label><UiSelect v-model="form.supplier_type"><option value="company">{{ tt('شركة / مؤسسة','Company') }}</option><option value="individual">{{ tt('فرد','Individual') }}</option></UiSelect></div>
      <div class="field"><label>{{ tt('اسم المورد','Supplier name') }} <span style="color:var(--primary)">*</span></label><input v-model="form.name" /></div>
      <div class="field"><label>{{ tt('كود المورد','Code') }}</label><input v-model="form.code" dir="ltr" :placeholder="tt('اختياري','Optional')" /></div>
      <div v-show="isCompany" class="field"><label>{{ tt('الشخص المسؤول','Contact person') }}</label><input v-model="form.contact_person" /></div>
      <div class="field"><label>{{ tt('التصنيف','Category') }}</label><UiSelect v-model="form.category"><option value="">—</option><option value="main">{{ tt('مورد رئيسي','Primary') }}</option><option value="secondary">{{ tt('مورد ثانوي','Secondary') }}</option><option value="services">{{ tt('خدمات','Services') }}</option><option value="importer">{{ tt('مستورد','Importer') }}</option></UiSelect></div>
    </div>

    <div class="card-title" style="margin-top:8px"><i class="ti ti-phone" /> {{ tt('بيانات التواصل','Contact details') }}</div>
    <div class="grid grid-4">
      <div class="field"><label>{{ tt('الهاتف','Phone') }}</label><input v-model="form.phone" dir="ltr" /></div>
      <div class="field"><label>{{ tt('هاتف إضافي','Alt. phone') }}</label><input v-model="form.phone2" dir="ltr" /></div>
      <div class="field"><label>{{ tt('واتساب','WhatsApp') }}</label><input v-model="form.whatsapp" dir="ltr" /></div>
      <div class="field"><label>{{ tt('البريد الإلكتروني','Email') }}</label><input v-model="form.email" dir="ltr" /></div>
    </div>

    <div class="card-title" style="margin-top:8px"><i class="ti ti-license" /> {{ tt('المستندات الرسمية','Official documents') }}</div>
    <div class="grid grid-2">
      <div class="field"><label>{{ tt('الرقم الضريبي','Tax number') }}</label><input v-model="form.tax_number" dir="ltr" /></div>
      <div class="field"><label>{{ tt('السجل التجاري','Commercial reg.') }}</label><input v-model="form.cr_number" dir="ltr" /></div>
    </div>

    <div class="card-title" style="margin-top:8px"><i class="ti ti-map-pin" /> {{ tt('العنوان','Address') }}</div>
    <div class="grid grid-3">
      <div class="field"><label>{{ tt('الدولة','Country') }}</label><input v-model="form.country" /></div>
      <div class="field"><label>{{ tt('المدينة','City') }}</label><input v-model="form.city" /></div>
      <div class="field"><label>{{ tt('المنطقة / الحي','Area / district') }}</label><input v-model="form.area" /></div>
    </div>
    <div class="field"><label>{{ tt('العنوان التفصيلي','Full address') }}</label><input v-model="form.address" /></div>

    <div class="card-title" style="margin-top:8px"><i class="ti ti-cash" /> {{ tt('الشروط المالية','Financial terms') }}</div>
    <div class="grid grid-3">
      <div class="field"><label>{{ tt('أيام السداد (آجل)','Payment terms (days)') }}</label><input v-model="form.payment_terms_days" type="number" min="0" max="365" dir="ltr" /></div>
      <div class="field"><label>{{ tt('رصيد افتتاحي (مستحق له)','Opening balance (payable)') }}</label><input v-model.number="form.opening_balance" type="number" step="0.001" dir="ltr" /></div>
      <div class="field"><label>{{ tt('الوسوم','Tags') }}</label><input v-model="form.tags" :placeholder="tt('افصل بفاصلة','comma separated')" /></div>
    </div>
    <div class="field"><label>{{ tt('بيانات التحويل البنكي','Bank details') }}</label><textarea v-model="form.bank_details" rows="2" :placeholder="tt('اسم البنك، رقم الحساب، IBAN','Bank name, account, IBAN')" /></div>

    <div class="card-title" style="margin-top:8px"><i class="ti ti-note" /> {{ tt('ملاحظات','Notes') }}</div>
    <div class="field"><textarea v-model="form.notes" rows="2" /></div>
    <label class="switch"><input v-model="form.is_active" type="checkbox" /><span /> {{ tt('نشط','Active') }}</label>
  </div>
</template>
