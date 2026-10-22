<script setup lang="ts">
/** Full customer profile form — generic fields covering every sector. */
const props = defineProps<{ form: any }>()
const auth = useAuth(); const { tt } = useI18n()
const sector = computed(() => auth.state.value.tenant?.sector_preset?.key || '')
// Sectors where official ID / nationality / birth date really matter.
const needsId = computed(() => ['hotel', 'chalets', 'halls', 'travel', 'car_rental', 'real_estate', 'car_showroom'].includes(sector.value))
const isCompany = computed(() => props.form.customer_type === 'company')
</script>

<template>
  <div>
    <div class="card-title"><i class="ti ti-id-badge-2" /> {{ tt('بيانات أساسية', 'Basic info') }}</div>
    <div class="grid grid-3">
      <div class="field">
        <label>{{ tt('نوع العميل', 'Customer type') }}</label>
        <UiSelect v-model="form.customer_type">
          <option value="individual">{{ tt('فرد', 'Individual') }}</option>
          <option value="company">{{ tt('شركة / جهة', 'Company') }}</option>
        </UiSelect>
      </div>
      <div class="field"><label>{{ tt('الاسم', 'Name') }} <span style="color:var(--primary)">*</span></label><input v-model="form.name" :placeholder="tt('اسم العميل', 'Customer name')" /></div>
      <div class="field"><label>{{ tt('كود العميل', 'Customer code') }}</label><input v-model="form.code" dir="ltr" :placeholder="tt('اختياري', 'Optional')" /></div>
    </div>

    <div class="card-title" style="margin-top:8px"><i class="ti ti-phone" /> {{ tt('بيانات التواصل', 'Contact details') }}</div>
    <div class="grid grid-3">
      <div class="field"><label>{{ tt('الهاتف', 'Phone') }}</label><input v-model="form.phone" dir="ltr" /></div>
      <div class="field"><label>{{ tt('هاتف إضافي', 'Alt. phone') }}</label><input v-model="form.phone2" dir="ltr" /></div>
      <div class="field"><label>{{ tt('واتساب', 'WhatsApp') }}</label><input v-model="form.whatsapp" dir="ltr" /></div>
      <div class="field"><label>{{ tt('البريد الإلكتروني', 'Email') }}</label><input v-model="form.email" dir="ltr" /></div>
    </div>

    <div class="card-title" style="margin-top:8px"><i class="ti ti-license" /> {{ tt('الهوية والمستندات', 'Identity & documents') }}</div>
    <div class="grid grid-3">
      <div class="field">
        <label>{{ tt('نوع الهوية', 'ID type') }}</label>
        <UiSelect v-model="form.id_type">
          <option value="">—</option>
          <option value="national_id">{{ tt('بطاقة مدنية / هوية', 'National ID') }}</option>
          <option value="passport">{{ tt('جواز سفر', 'Passport') }}</option>
          <option value="resident_id">{{ tt('إقامة', 'Residency') }}</option>
          <option value="commercial_reg">{{ tt('سجل تجاري', 'Commercial reg.') }}</option>
        </UiSelect>
      </div>
      <div class="field">
        <label>{{ tt('رقم الهوية / المستند', 'ID / document no.') }} <span v-if="needsId" class="muted" style="font-weight:400">{{ tt('(مطلوب للعقود)', '(required for contracts)') }}</span></label>
        <input v-model="form.id_number" dir="ltr" />
      </div>
      <div class="field"><label>{{ tt('الرقم الضريبي', 'Tax number') }}</label><input v-model="form.tax_number" dir="ltr" /></div>
      <div class="field"><label>{{ tt('الجنسية', 'Nationality') }}</label><input v-model="form.nationality" /></div>
      <div class="field"><label>{{ tt('تاريخ الميلاد', 'Date of birth') }}</label><input v-model="form.birth_date" type="date" dir="ltr" /></div>
      <div class="field">
        <label>{{ tt('الجنس', 'Gender') }}</label>
        <UiSelect v-model="form.gender"><option value="">—</option><option value="male">{{ tt('ذكر', 'Male') }}</option><option value="female">{{ tt('أنثى', 'Female') }}</option></UiSelect>
      </div>
    </div>

    <div v-show="isCompany" class="card-title" style="margin-top:8px"><i class="ti ti-building" /> {{ tt('بيانات الجهة', 'Company info') }}</div>
    <div v-show="isCompany" class="grid grid-2">
      <div class="field"><label>{{ tt('اسم الشركة / الجهة', 'Company name') }}</label><input v-model="form.company_name" /></div>
      <div class="field"><label>{{ tt('الشخص المسؤول', 'Contact person') }}</label><input v-model="form.contact_person" /></div>
    </div>

    <div class="card-title" style="margin-top:8px"><i class="ti ti-map-pin" /> {{ tt('العنوان', 'Address') }}</div>
    <div class="grid grid-3">
      <div class="field"><label>{{ tt('الدولة', 'Country') }}</label><input v-model="form.country" /></div>
      <div class="field"><label>{{ tt('المدينة / الولاية', 'City / state') }}</label><input v-model="form.city" /></div>
      <div class="field"><label>{{ tt('المنطقة / الحي', 'Area / district') }}</label><input v-model="form.area" /></div>
    </div>
    <div class="field"><label>{{ tt('العنوان التفصيلي', 'Full address') }}</label><input v-model="form.address" /></div>

    <div class="card-title" style="margin-top:8px"><i class="ti ti-cash" /> {{ tt('التصنيف والبيانات المالية', 'Classification & financials') }}</div>
    <div class="grid grid-4">
      <div class="field">
        <label>{{ tt('التصنيف', 'Category') }}</label>
        <UiSelect v-model="form.category">
          <option value="">{{ tt('عادي', 'Regular') }}</option>
          <option value="vip">{{ tt('مميّز (VIP)', 'VIP') }}</option>
          <option value="wholesale">{{ tt('جملة', 'Wholesale') }}</option>
          <option value="retail">{{ tt('تجزئة', 'Retail') }}</option>
          <option value="government">{{ tt('جهة حكومية', 'Government') }}</option>
        </UiSelect>
      </div>
      <div class="field"><label>{{ tt('حد الائتمان', 'Credit limit') }}</label><input v-model="form.credit_limit" type="number" step="0.001" min="0" dir="ltr" /></div>
      <div class="field"><label>{{ tt('رصيد افتتاحي', 'Opening balance') }}</label><input v-model="form.opening_balance" type="number" step="0.001" dir="ltr" /></div>
      <div class="field"><label>{{ tt('أيام السداد (آجل)', 'Payment terms (days)') }}</label><input v-model="form.payment_terms_days" type="number" min="0" max="365" dir="ltr" /></div>
    </div>
    <div class="field"><label>{{ tt('الوسوم (افصل بينها بفاصلة)', 'Tags (comma separated)') }}</label><input v-model="form.tags" :placeholder="tt('مثال: عميل دائم, مشروع المول', 'e.g. regular, mall project')" /></div>

    <div class="card-title" style="margin-top:8px"><i class="ti ti-note" /> {{ tt('ملاحظات', 'Notes') }}</div>
    <div class="field"><textarea v-model="form.notes" rows="3" /></div>

    <label class="switch"><input v-model="form.is_active" type="checkbox" /><span /> {{ tt('نشط', 'Active') }}</label>
  </div>
</template>
