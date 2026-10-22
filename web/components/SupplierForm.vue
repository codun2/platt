<script setup lang="ts">
/** Full supplier profile form (new + edit). */
const props = defineProps<{ form: any }>()
const isCompany = computed(() => props.form.supplier_type === 'company')
</script>

<template>
  <div>
    <div class="card-title"><i class="ti ti-truck" /> بيانات أساسية</div>
    <div class="grid grid-3">
      <div class="field"><label>نوع المورد</label><UiSelect v-model="form.supplier_type"><option value="company">شركة / مؤسسة</option><option value="individual">فرد</option></UiSelect></div>
      <div class="field"><label>اسم المورد <span style="color:var(--primary)">*</span></label><input v-model="form.name" /></div>
      <div class="field"><label>كود المورد</label><input v-model="form.code" dir="ltr" placeholder="اختياري" /></div>
      <div v-show="isCompany" class="field"><label>الشخص المسؤول</label><input v-model="form.contact_person" /></div>
      <div class="field"><label>التصنيف</label><UiSelect v-model="form.category"><option value="">—</option><option value="main">مورد رئيسي</option><option value="secondary">مورد ثانوي</option><option value="services">خدمات</option><option value="importer">مستورد</option></UiSelect></div>
    </div>

    <div class="card-title" style="margin-top:8px"><i class="ti ti-phone" /> بيانات التواصل</div>
    <div class="grid grid-4">
      <div class="field"><label>الهاتف</label><input v-model="form.phone" dir="ltr" /></div>
      <div class="field"><label>هاتف إضافي</label><input v-model="form.phone2" dir="ltr" /></div>
      <div class="field"><label>واتساب</label><input v-model="form.whatsapp" dir="ltr" /></div>
      <div class="field"><label>البريد الإلكتروني</label><input v-model="form.email" dir="ltr" /></div>
    </div>

    <div class="card-title" style="margin-top:8px"><i class="ti ti-license" /> المستندات الرسمية</div>
    <div class="grid grid-2">
      <div class="field"><label>الرقم الضريبي</label><input v-model="form.tax_number" dir="ltr" /></div>
      <div class="field"><label>السجل التجاري</label><input v-model="form.cr_number" dir="ltr" /></div>
    </div>

    <div class="card-title" style="margin-top:8px"><i class="ti ti-map-pin" /> العنوان</div>
    <div class="grid grid-3">
      <div class="field"><label>الدولة</label><input v-model="form.country" /></div>
      <div class="field"><label>المدينة</label><input v-model="form.city" /></div>
      <div class="field"><label>المنطقة / الحي</label><input v-model="form.area" /></div>
    </div>
    <div class="field"><label>العنوان التفصيلي</label><input v-model="form.address" /></div>

    <div class="card-title" style="margin-top:8px"><i class="ti ti-cash" /> الشروط المالية</div>
    <div class="grid grid-3">
      <div class="field"><label>أيام السداد (آجل)</label><input v-model="form.payment_terms_days" type="number" min="0" max="365" dir="ltr" /></div>
      <div class="field"><label>رصيد افتتاحي (مستحق له)</label><input v-model.number="form.opening_balance" type="number" step="0.001" dir="ltr" /></div>
      <div class="field"><label>الوسوم</label><input v-model="form.tags" placeholder="افصل بفاصلة" /></div>
    </div>
    <div class="field"><label>بيانات التحويل البنكي</label><textarea v-model="form.bank_details" rows="2" placeholder="اسم البنك، رقم الحساب، IBAN" /></div>

    <div class="card-title" style="margin-top:8px"><i class="ti ti-note" /> ملاحظات</div>
    <div class="field"><textarea v-model="form.notes" rows="2" /></div>
    <label class="switch"><input v-model="form.is_active" type="checkbox" /><span /> نشط</label>
  </div>
</template>
